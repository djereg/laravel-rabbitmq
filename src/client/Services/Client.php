<?php

namespace Djereg\Laravel\RabbitMQ\RPC\Client\Services;

use Datto\JsonRpc\Client as JsonRpcClient;
use Datto\JsonRpc\Responses\ErrorResponse;
use Datto\JsonRpc\Responses\Response as JsonRpcResponse;
use Datto\JsonRpc\Responses\ResultResponse;
use Djereg\Laravel\RabbitMQ\Core\Events\MessagePublished;
use Djereg\Laravel\RabbitMQ\Core\Events\MessagePublishing;
use Djereg\Laravel\RabbitMQ\RPC\Client\Exceptions\ClientException;
use Djereg\Laravel\RabbitMQ\RPC\Client\Exceptions\RequestException;
use Djereg\Laravel\RabbitMQ\RPC\Client\Exceptions\ServerException;
use Djereg\Laravel\RabbitMQ\RPC\Client\Exceptions\TimeoutException;
use Djereg\Laravel\RabbitMQ\RPC\Client\Exceptions\TransportException;
use ErrorException;
use Illuminate\Support\Str;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Exception\AMQPConnectionClosedException;
use PhpAmqpLib\Exception\AMQPOutOfBoundsException;
use PhpAmqpLib\Exception\AMQPRuntimeException;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Message\AMQPMessage;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\RabbitMQQueue;

class Client
{
    private const DIRECT_REPLY_TO = 'amq.rabbitmq.reply-to';

    private ?string $consumerTag = null;

    private ?AMQPMessage $message = null;
    private ?AMQPChannel $channel = null;

    private readonly JsonRpcClient $client;

    public function __construct(
        private readonly string $service,
        private readonly RabbitMQQueue $queue,
    ) {
        $this->client = new JsonRpcClient();
    }

    /**
     * @param string $name
     * @param array $arguments
     *
     * @return mixed
     * @throws ClientException
     * @throws RequestException
     * @throws ServerException
     * @throws TimeoutException
     * @throws TransportException
     */
    public function __call(string $name, array $arguments)
    {
        return $this->call($name, $arguments);
    }

    /**
     * @param string|int $id The request ID.
     * @param string $method The method to call on the service.
     * @param array $arguments The arguments to pass to the method.
     *
     * @return $this
     */
    public function query(string|int $id, string $method, array $arguments = []): static
    {
        $this->client->query($id, $method, $arguments);
        return $this;
    }

    /**
     * @param string $method The method to call on the service.
     * @param array $arguments The arguments to pass to the method.
     *
     * @return $this
     */
    public function notify(string $method, array $arguments = []): static
    {
        $this->client->notify($method, $arguments);
        return $this;
    }

    /**
     * @param string $method
     * @param array $payload
     * @param int $timeout
     *
     * @return mixed
     * @throws ClientException
     * @throws RequestException
     * @throws ServerException
     * @throws TimeoutException
     * @throws TransportException
     */
    public function call(string $method, array $payload, int $timeout = 30): mixed
    {
        $response = $this->query(1, $method, $payload)->send($timeout)[0];

        return $response->throw()->value();
    }

    /**
     * @param int $timeout Maximum time to wait for a response in seconds.
     *
     * @return Response[]
     * @throws ClientException
     * @throws ServerException
     * @throws TimeoutException
     * @throws TransportException
     */
    public function send(int $timeout = 30): array
    {
        $this->initQueue();
        $uuid = Str::uuid()->toString();

        $ttl = round($timeout / 2, 3) * 1000;
        $message = $this->createMessage($uuid, $ttl);

        try {
            $this->publish($message);
        } catch (AMQPRuntimeException $e) {
            throw new TransportException('The RPC transport error occurred.', $e);
        }

        try {
            $message = $this->wait($uuid, $timeout);
        } catch (AMQPTimeoutException $e) {
            throw new TimeoutException('The server took too long to respond.', $e);
        } catch (AMQPRuntimeException $e) {
            throw new TransportException('The RPC transport error occurred.', $e);
        }

        try {
            $response = $this->client->decode($message->body);
        } catch (ErrorException $e) {
            throw new ServerException('Failed to decode the response.', $e);
        }

        return array_map(fn($r) => $this->mapResponse($r), $response);
    }

    private function channel(): AMQPChannel
    {
        return $this->channel ??= $this->queue->getConnection()->channel();
    }

    private function initQueue(): void
    {
        if ($this->consumerTag) {
            return;
        }

        $channel = $this->channel();

        $tag = $channel->basic_consume(
            queue: self::DIRECT_REPLY_TO,
            consumer_tag: '',
            no_local: false,
            no_ack: true,
            exclusive: true,
            callback: fn($m) => $this->onMessage($m),
        );

        $this->consumerTag = $tag;
    }

    /**
     * @param string $uuid
     * @param int $timeout
     *
     * @return AMQPMessage
     * @throws AMQPRuntimeException
     * @throws AMQPTimeoutException
     * @throws AMQPOutOfBoundsException
     * @throws AMQPConnectionClosedException
     */
    private function wait(string $uuid, int $timeout): AMQPMessage
    {
        $this->message = null;

        $channel = $this->channel();
        while ($this->message?->get('correlation_id') !== $uuid) {
            $channel->wait(timeout: $timeout);
        }

        return $this->message;
    }

    private function onMessage(AMQPMessage $message): void
    {
        $this->message = $message;
    }

    private function createMessage(string $uuid, int $ttl): AMQPMessage
    {
        $body = $this->client->encode();

        return new AMQPMessage(
            body: $body,
            properties: [
                'content_type'        => 'application/json',
                'delivery_mode'       => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                'reply_to'            => self::DIRECT_REPLY_TO,
                'correlation_id'      => $uuid,
                'expiration'          => (string)$ttl,
                'application_headers' => [
                    'X-Message-Type' => ['S', 'rpc'],
                ],
            ]
        );
    }

    private function mapResponse(JsonRpcResponse $response): Response
    {
        if ($response instanceof ErrorResponse) {
            return new Response(
                id: $response->getId(),
                error: [
                    'code'    => $response->getCode(),
                    'message' => $response->getMessage(),
                    'data'    => $response->getData(),
                ],
            );
        }

        assert($response instanceof ResultResponse);

        return new Response(
            id: $response->getId(),
            value: $response->getValue()
        );
    }

    private function publish(AMQPMessage $message): void
    {
        event(new MessagePublishing($message, '', $this->service));
        $this->channel()->basic_publish($message, '', $this->service);
        event(new MessagePublished($message, '', $this->service));
    }
}

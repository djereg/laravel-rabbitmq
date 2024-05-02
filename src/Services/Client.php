<?php

namespace Djereg\Laravel\RabbitMQ\Services;

use Datto\JsonRpc\Client as JsonRpcClient;
use Datto\JsonRpc\Responses\ErrorResponse;
use Datto\JsonRpc\Responses\Response as JsonRpcResponse;
use Datto\JsonRpc\Responses\ResultResponse;
use Djereg\Laravel\RabbitMQ\Exceptions\ClientException;
use Djereg\Laravel\RabbitMQ\Exceptions\RequestException;
use Djereg\Laravel\RabbitMQ\Exceptions\ServerException;
use Djereg\Laravel\RabbitMQ\Exceptions\TimeoutException;
use Djereg\Laravel\RabbitMQ\Exceptions\TransportException;
use Djereg\Laravel\RabbitMQ\Queues\RabbitMQQueue as Queue;
use Djereg\Laravel\RabbitMQ\Responses\Response;
use ErrorException;
use Illuminate\Support\Str;
use PhpAmqpLib\Channel\AMQPChannel as Channel;
use PhpAmqpLib\Exception\AMQPIOException;
use PhpAmqpLib\Exception\AMQPNoDataException;
use PhpAmqpLib\Exception\AMQPRuntimeException;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Message\AMQPMessage as Message;
use PhpAmqpLib\Wire\AMQPTable;

class Client
{
    private const DIRECT_REPLY_TO = 'amq.rabbitmq.reply-to';

    private ?Message $message = null;
    private ?Channel $channel = null;

    private readonly JsonRpcClient $client;

    private string $consumerTag = '';

    public function __construct(
        private readonly string $service,
        private readonly Queue $queue,
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
        $uuid = Str::uuid()->toString();
        $message = $this->createMessage($uuid, $timeout * 1000);

        $this->setupConsumer();

        try {
            $this->publish($message);
        } catch (AMQPRuntimeException $e) {
            throw new TransportException('The RPC transport error occurred.', $e);
        }

        try {
            $message = $this->consume($uuid, $timeout);
        } catch (AMQPRuntimeException $e) {
            throw new TransportException('RPC transport error occurred.', $e);
        } catch (AMQPIOException $e) {
            throw new TransportException('RPC communication error occurred.', $e);
        }

        try {
            $response = $this->client->decode($message->getBody());
        } catch (ErrorException $e) {
            throw new ServerException('Failed to decode the response.', $e);
        }

        return array_map(fn($r) => $this->mapResponse($r), $response);
    }

    private function getChannel(): Channel
    {
        return $this->channel ??= $this->queue->getConnection()->channel();
    }

    private function setupConsumer(): void
    {
        if ($this->consumerTag) {
            return;
        }

        $channel = $this->getChannel();

        $tag = $channel->basic_consume(
            queue: self::DIRECT_REPLY_TO,
            consumer_tag: $this->consumerTag,
            no_ack: true,
            exclusive: true,
            callback: function (Message $message) {
                $this->message = $message;
            },
        );

        $this->consumerTag = $tag;
    }

    /**
     * @param string $uuid
     * @param int $timeout
     *
     * @return Message
     * @throws AMQPIOException
     * @throws TimeoutException
     */
    private function consume(string $uuid, int $timeout): Message
    {
        $this->message = null;

        $stopTime = time() + $timeout;
        $waitTimeout = $this->queue->getWaitTimeout();

        $channel = $this->getChannel();
        $connection = $channel->getConnection();

        while ($channel->is_consuming() || $channel->hasPendingMethods()) {

            if ($stopTime < microtime(true)) {
                throw new TimeoutException('The server took too long to respond.');
            }

            try {
                $channel->wait(null, false, $waitTimeout);
            } catch (AMQPTimeoutException $e) {
                // something might be wrong, try to send heartbeat which involves select+write
                $connection->checkHeartBeat();
                continue;
            } catch (AMQPNoDataException $e) {
                continue;
            }

            if ($this->message?->get('correlation_id') === $uuid) {
                break;
            }
        }

        return $this->message;
    }

    private function onMessage(Message $message): void
    {
        $this->message = $message;
    }

    private function createMessage(string $uuid, int $ttl): Message
    {
        $body = $this->client->encode();

        return new Message(
            body: $body,
            properties: [
                'content_type'        => 'application/json',
                'delivery_mode'       => Message::DELIVERY_MODE_PERSISTENT,
                'reply_to'            => self::DIRECT_REPLY_TO,
                'correlation_id'      => $uuid,
                'expiration'          => (string)$ttl,
                'application_headers' => new AMQPTable([
                    'X-Message-Type' => 'request',
                    'Content-Type'   => 'application/json',
                ]),
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

    private function publish(Message $message): void
    {
        $this->queue->publishMessage($message, $this->service, '', $this->getChannel());
    }
}

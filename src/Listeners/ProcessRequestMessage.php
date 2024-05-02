<?php

namespace Djereg\Laravel\RabbitMQ\Listeners;

use Datto\JsonRpc\Server;
use Djereg\Laravel\RabbitMQ\Events\MessageReceived;
use Djereg\Laravel\RabbitMQ\Queues\RabbitMQQueue as Queue;
use PhpAmqpLib\Message\AMQPMessage as Message;
use PhpAmqpLib\Wire\AMQPTable;

class ProcessRequestMessage extends MessageHandler
{
    public function __construct(
        private readonly Queue $queue,
        private readonly Server $server,
    ) {
        //
    }

    protected function shouldHandle(string $type): bool
    {
        return $type === 'request';
    }

    protected function handleMessage(MessageReceived $event): void
    {
        $response = $this->server->reply($event->body);
        $replyTo = $event->properties->get('reply_to');

        $message = new Message(
            body: $response,
            properties: [
                'content_type'        => 'application/json',
                'delivery_mode'       => Message::DELIVERY_MODE_NON_PERSISTENT,
                'correlation_id'      => $event->properties->get('correlation_id'),
                'application_headers' => new AMQPTable([
                    'X-Message-Type' => 'response',
                    'Content-Type'   => 'application/json',
                ]),
            ]
        );

        $this->queue->publishMessage($message, $replyTo, '');
    }
}

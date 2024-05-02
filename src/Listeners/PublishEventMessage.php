<?php

namespace Djereg\Laravel\RabbitMQ\Listeners;

use Djereg\Laravel\RabbitMQ\Contracts\PublishEventInterface;
use Djereg\Laravel\RabbitMQ\Queues\RabbitMQQueue as Queue;
use PhpAmqpLib\Message\AMQPMessage as Message;
use PhpAmqpLib\Wire\AMQPTable;

readonly class PublishEventMessage
{
    public function __construct(
        private Queue $queue,
    ) {
        //
    }

    public function handle(PublishEventInterface $event): void
    {
        $message = new Message(
            body: json_encode($event->payload()),
            properties: [
                'content_type'        => 'application/json',
                'delivery_mode'       => Message::DELIVERY_MODE_PERSISTENT,
                'application_headers' => new AMQPTable([
                    'X-Message-Type' => 'event',
                    'X-Event-Name'   => $event->event(),
                    'Content-Type'   => 'application/json',
                ]),
            ]
        );

        if ($queue = $event->queue()) {
            $this->queue->publishMessage($message, $queue, '');
        } else {
            $this->queue->publishMessage($message, $event->event());
        }
    }
}

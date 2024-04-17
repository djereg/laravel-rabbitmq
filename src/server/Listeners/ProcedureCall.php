<?php

namespace Djereg\Laravel\RabbitMQ\RPC\Server\Listeners;

use Datto\JsonRpc\Server;
use Djereg\Laravel\RabbitMQ\RPC\Server\Events\ProcedureEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use PhpAmqpLib\Message\AMQPMessage;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\RabbitMQQueue;

final readonly class ProcedureCall implements ShouldQueue
{
    public function __construct(
        private Server $server,
        private RabbitMQQueue $queue,
    ) {
        //
    }

    public function handle(ProcedureEvent $event): void
    {
        $response = $this->server->reply($event->payload);

        $message = new AMQPMessage(
            body: $response,
            properties: [
                'content_type'   => 'application/json',
                'delivery_mode'  => AMQPMessage::DELIVERY_MODE_NON_PERSISTENT,
                'correlation_id' => $event->correlationId,
            ]
        );

        $channel = $this->queue->getChannel();
        $channel->basic_publish($message, '', $event->replyTo);
    }
}

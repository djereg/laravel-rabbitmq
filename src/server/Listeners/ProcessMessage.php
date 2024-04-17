<?php

namespace Djereg\Laravel\RabbitMQ\RPC\Server\Listeners;

use Djereg\Laravel\RabbitMQ\Core\Events\MessageProcessed;
use Djereg\Laravel\RabbitMQ\Core\Events\MessageProcessing;
use Djereg\Laravel\RabbitMQ\Core\Events\MessageReceived;
use Djereg\Laravel\RabbitMQ\Core\Services\ArrayBag;
use Djereg\Laravel\RabbitMQ\RPC\Server\Events\ProcedureEvent;
use ErrorException;
use Illuminate\Contracts\Events\Dispatcher;
use JsonException;
use PhpAmqpLib\Message\AMQPMessage;

final readonly class ProcessMessage
{
    public function __construct(
        private Dispatcher $events,
    ) {
        //
    }

    /**
     * @param MessageReceived $event
     *
     * @throws JsonException
     * @throws ErrorException
     */
    public function handle(MessageReceived $event): void
    {
        $type = $event->headers->get('X-Message-Type');

        if ($type !== 'rpc') {
            return;
        }

        $this->events->dispatch(new MessageProcessing($event));
        $this->handleMessage($event->headers, $event->message);
        $this->events->dispatch(new MessageProcessed($event));
    }

    /**
     * @param ArrayBag $headers
     * @param AMQPMessage $message
     *
     * @return void
     * @throws ErrorException
     * @throws JsonException
     */
    private function handleMessage(ArrayBag $headers, AMQPMessage $message): void
    {
        $payload = $message->getBody();
        $replyTo = $message->get('reply_to');
        $correlationId = $message->get('correlation_id');

        $this->events->dispatch(
            new ProcedureEvent($headers, $payload, $replyTo, $correlationId)
        );
    }
}

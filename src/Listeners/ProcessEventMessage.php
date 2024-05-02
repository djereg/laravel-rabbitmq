<?php

namespace Djereg\Laravel\RabbitMQ\Listeners;

use Djereg\Laravel\RabbitMQ\Events\MessageEvent;
use Djereg\Laravel\RabbitMQ\Events\MessageReceived;
use Illuminate\Contracts\Events\Dispatcher;

class ProcessEventMessage extends MessageHandler
{
    public function __construct(
        private readonly Dispatcher $events,
    ) {
        //
    }

    protected function shouldHandle(string $type): bool
    {
        return $type === 'event';
    }

    protected function handleMessage(MessageReceived $event): void
    {
        $name = $event->headers->get('X-Event-Name');
        $payload = $this->getDecodedBody($event);

        $this->events->dispatch(
            new MessageEvent($name, $event->headers, $payload)
        );
    }
}

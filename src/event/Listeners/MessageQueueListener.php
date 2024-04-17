<?php

namespace Djereg\Laravel\RabbitMQ\Event\Listeners;

use BadMethodCallException;
use Djereg\Laravel\RabbitMQ\Event\Events\MessageQueueEvent;
use Djereg\Laravel\RabbitMQ\Event\Events\RegisterBindingKeys;
use Djereg\Laravel\RabbitMQ\Event\Events\RegisterListenerKeys;
use Illuminate\Contracts\Queue\ShouldQueue;
use function str;

abstract class MessageQueueListener implements ShouldQueue
{
    protected array $listen = [];

    public function shouldQueue($event): bool
    {
        if ($event instanceof RegisterListenerKeys) {
            return true;
        }

        return $this->shouldRun($event);
    }

    final public function handle(MessageQueueEvent $event): void
    {
        if (!$this->shouldRun($event)) {
            return;
        }

        $method = $this->getEventMethodName($event);
        if (method_exists($this, $method)) {
            call_user_func([$this, $method], $event);
            return;
        }

        $this->onEvent($event);
    }

    final public function handleRegisterKeys(RegisterListenerKeys $e): void
    {
        event(new RegisterBindingKeys($this->listen));
    }

    protected function shouldRun(MessageQueueEvent $event): bool
    {
        return in_array($event->event, $this->listen);
    }

    protected function onEvent(MessageQueueEvent $event): void
    {
        throw new BadMethodCallException(
            'You have to implement the onEvent method or the on{EventName} method in your listener'
        );
    }

    private function getEventMethodName(MessageQueueEvent $event): string
    {
        return str($event->event)->replace([':', '.'], ' ')->studly()->prepend('on');
    }
}

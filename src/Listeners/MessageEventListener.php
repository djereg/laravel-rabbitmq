<?php

namespace Djereg\Laravel\RabbitMQ\Listeners;

use BadMethodCallException;
use Djereg\Laravel\RabbitMQ\Events\MessageEvent;

use function str;

abstract class MessageEventListener
{
    public static array $listen = [];

    public function shouldQueue($event): bool
    {
        return $this->shouldRun($event);
    }

    final public function handle(MessageEvent $event): void
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

    protected function shouldRun(MessageEvent $event): bool
    {
        return in_array($event->event, static::$listen);
    }

    protected function onEvent(MessageEvent $event): void
    {
        throw new BadMethodCallException(
            'You have to implement the onEvent method or the on{EventName} method in your listener'
        );
    }

    private function getEventMethodName(MessageEvent $event): string
    {
        return str($event->event)->replace([':', '.'], ' ')->studly()->prepend('on');
    }
}

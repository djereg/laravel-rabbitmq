<?php

namespace Djereg\Laravel\RabbitMQ\Event\Events;

use Djereg\Laravel\RabbitMQ\Event\Contracts\PublishEvent;

abstract class MessagePublishEvent implements PublishEvent
{
    protected string $event;
    protected string $queue;

    public function event(): ?string
    {
        return $this->event ?? null;
    }

    public function queue(): ?string
    {
        return $this->queue ?? null;
    }

    public function payload(): array
    {
        return [];
    }
}

<?php

namespace Djereg\Laravel\RabbitMQ\Events;

use Djereg\Laravel\RabbitMQ\Contracts\PublishEventInterface;

abstract class MessagePublishEvent implements PublishEventInterface
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

<?php

namespace Djereg\Laravel\RabbitMQ\Event\Contracts;

interface PublishEvent
{
    public function event(): ?string;

    public function queue(): ?string;

    public function payload(): array;
}

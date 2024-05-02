<?php

namespace Djereg\Laravel\RabbitMQ\Contracts;

interface PublishEventInterface
{
    public function event(): ?string;

    public function queue(): ?string;

    public function payload(): array;
}

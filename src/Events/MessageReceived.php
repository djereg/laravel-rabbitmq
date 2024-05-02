<?php

namespace Djereg\Laravel\RabbitMQ\Events;

use Djereg\Laravel\RabbitMQ\Services\ArrayBag;

readonly class MessageReceived
{
    public function __construct(
        public string $body,
        public ArrayBag $headers,
        public ArrayBag $properties,
    ) {
        //
    }
}

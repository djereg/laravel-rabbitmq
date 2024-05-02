<?php

namespace Djereg\Laravel\RabbitMQ\Events;

use Djereg\Laravel\RabbitMQ\Services\ArrayBag;

readonly class MessageProcessed
{
    public string $body;
    public ArrayBag $headers;
    public ArrayBag $properties;

    public function __construct(MessageReceived $event)
    {
        $this->body = $event->body;
        $this->headers = $event->headers;
        $this->properties = $event->properties;
    }
}

<?php

namespace Djereg\Laravel\RabbitMQ\Core\Events;

use Djereg\Laravel\RabbitMQ\Core\Services\ArrayBag;
use PhpAmqpLib\Message\AMQPMessage;

readonly class MessageProcessed
{
    public ArrayBag $headers;
    public AMQPMessage $message;

    public function __construct(MessageReceived $event)
    {
        $this->headers = $event->headers;
        $this->message = $event->message;
    }
}

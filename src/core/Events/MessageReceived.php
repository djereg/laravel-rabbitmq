<?php

namespace Djereg\Laravel\RabbitMQ\Core\Events;

use Djereg\Laravel\RabbitMQ\Core\Services\ArrayBag;
use PhpAmqpLib\Message\AMQPMessage;

readonly class MessageReceived
{
    public function __construct(
        public ArrayBag $headers,
        public AMQPMessage $message,
    ) {
        //
    }
}

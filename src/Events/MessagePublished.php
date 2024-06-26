<?php

namespace Djereg\Laravel\RabbitMQ\Events;

use PhpAmqpLib\Message\AMQPMessage;

readonly class MessagePublished
{
    public function __construct(
        public AMQPMessage $message,
        public string $exchange,
        public string $routingKey,
    ) {
        //
    }
}

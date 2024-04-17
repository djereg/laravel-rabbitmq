<?php

namespace Djereg\Laravel\RabbitMQ\Core\Events;

use PhpAmqpLib\Message\AMQPMessage;

readonly class MessagePublishing
{
    public function __construct(
        public AMQPMessage $message,
        public string $exchange,
        public string $routingKey,
    ) {
        //
    }
}

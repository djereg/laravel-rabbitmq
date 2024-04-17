<?php

namespace Djereg\Laravel\RabbitMQ\Event\Events;

final readonly class RegisterBindingKeys
{
    public function __construct(
        public array $keys,
    ) {
        //
    }
}

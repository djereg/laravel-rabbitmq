<?php

namespace Djereg\Laravel\RabbitMQ\RPC\Server\Events;

final readonly class ProcedureCall
{
    public function __construct(
        public string $method,
        public array $arguments,
    ) {
        //
    }
}

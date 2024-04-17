<?php

namespace Djereg\Laravel\RabbitMQ\RPC\Server\Events;

final readonly class ProcedureCalling
{
    public string $method;
    public array $arguments;

    public function __construct(ProcedureCall $event)
    {
        $this->method = $event->method;
        $this->arguments = $event->arguments;
    }
}

<?php

namespace Djereg\Laravel\RabbitMQ\RPC\Server\Events;

use Djereg\Laravel\RabbitMQ\Core\Services\ArrayBag;

final readonly class ProcedureEvent
{
    public function __construct(
        public ArrayBag $headers,
        public string $payload,
        public string $replyTo,
        public string $correlationId,
    ) {
        //
    }
}

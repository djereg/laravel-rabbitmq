<?php

namespace Djereg\Laravel\RabbitMQ\Events;

use Djereg\Laravel\RabbitMQ\Services\ArrayBag;

readonly class MessageEvent
{
    public function __construct(
        public string $event,
        public ArrayBag $headers,
        public ArrayBag $payload,
    ) {
        //
    }

    public function all(): array
    {
        return $this->payload->all();
    }

    public function has(string $key): bool
    {
        return $this->payload->has($key);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->payload->get($key, $default);
    }

    public function first(array $keys, mixed $default = null): mixed
    {
        return $this->payload->first($keys, $default);
    }
}

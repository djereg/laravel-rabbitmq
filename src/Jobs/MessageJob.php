<?php

namespace Djereg\Laravel\RabbitMQ\Jobs;

use Djereg\Laravel\RabbitMQ\Events\MessageReceived;
use Djereg\Laravel\RabbitMQ\Services\ArrayBag;
use Illuminate\Contracts\Events\Dispatcher;

readonly class MessageJob
{
    public function __construct(
        private string $body,
        private array $headers,
        private array $properties,
    ) {
        //
    }

    public function __invoke(Dispatcher $dispatcher): void
    {
        $headers = new ArrayBag($this->headers);
        $properties = new ArrayBag($this->properties);

        $dispatcher->dispatch(
            new MessageReceived($this->body, $headers, $properties)
        );
    }
}

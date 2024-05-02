<?php

namespace Djereg\Laravel\RabbitMQ\Events;

use OutOfBoundsException;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

readonly class MessagePublishing
{
    public function __construct(
        public AMQPMessage $message,
        public string $exchange,
        public string $routingKey,
    ) {
        //
    }

    public function getHeader(string $name, mixed $default = null): mixed
    {
        try {
            $headers = $this->message->get('application_headers');
            return $headers[$name] ?? value($default);
        } catch (OutOfBoundsException $e) {
            return value($default);
        }
    }

    public function setHeaders(array $headers): void
    {
        /** @var AMQPTable $appHeaders */
        $appHeaders = $this->message->get('application_headers');
        foreach ($headers as $name => $value) {
            $appHeaders->set($name, $value);
        }
        $this->message->set('application_headers', $appHeaders);
    }
}

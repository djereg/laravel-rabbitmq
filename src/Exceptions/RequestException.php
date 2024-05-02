<?php

namespace Djereg\Laravel\RabbitMQ\Exceptions;

use Throwable;

class RequestException extends ClientException
{
    public function __construct(
        string $message,
        int $code = 0,
        ?Throwable $previous = null,
        public readonly mixed $data = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getData(): mixed
    {
        return $this->data;
    }
}

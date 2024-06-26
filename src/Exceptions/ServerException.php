<?php

namespace Djereg\Laravel\RabbitMQ\Exceptions;

use Djereg\Laravel\RabbitMQ\Services\ArrayBag;
use Throwable;

class ServerException extends ProcedureCallException
{
    private readonly ArrayBag $error;

    public function __construct(string $message, Throwable $previous = null, ?ArrayBag $error = null)
    {
        parent::__construct($message, 0, $previous);
        $this->error = $error ?? new ArrayBag([]);
    }

    public function getError(): ArrayBag
    {
        return $this->error;
    }
}

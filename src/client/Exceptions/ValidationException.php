<?php

namespace Djereg\Laravel\RabbitMQ\RPC\Client\Exceptions;

use Illuminate\Contracts\Support\MessageBag;
use Illuminate\Support\MessageBag as ErrorBag;

class ValidationException extends RequestException
{
    public function getErrors(): MessageBag
    {
        return new ErrorBag($this->getData());
    }
}

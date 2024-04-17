<?php

namespace Djereg\Laravel\RabbitMQ\RPC\Server\Exceptions;

use Datto\JsonRpc\Exceptions\Exception;
use Datto\JsonRpc\Responses\ErrorResponse;

class ArgumentException extends Exception
{
    public function __construct(string $message)
    {
        parent::__construct($message, ErrorResponse::INVALID_ARGUMENTS);
    }
}

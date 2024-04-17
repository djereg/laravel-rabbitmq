<?php

namespace Djereg\Laravel\RabbitMQ\RPC\Server\Listeners;

use Djereg\Laravel\RabbitMQ\RPC\Server\Events\ProcedureCall;
use Djereg\Laravel\RabbitMQ\RPC\Server\Exceptions\ArgumentException;

abstract class Procedure
{
    protected string $method;

    /**
     * @param ProcedureCall $event
     *
     * @return array|null
     * @throws ArgumentException
     */
    final public function handle(ProcedureCall $event): mixed
    {
        if (!$this->__shouldRun($event)) {
            return null;
        }

        if (!method_exists($this, '__invoke')) {
            return null;
        }

        try {
            return call_user_func_array([$this, '__invoke'], $event->arguments);
        } catch (\Error $e) {
            throw new ArgumentException($e->getMessage());
        }
    }

    protected function __shouldRun(ProcedureCall $event): bool
    {
        return $this->method === $event->method;
    }
}

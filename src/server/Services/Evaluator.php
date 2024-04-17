<?php

namespace Djereg\Laravel\RabbitMQ\RPC\Server\Services;

use Datto\JsonRpc\Evaluator as BaseEvaluator;
use Datto\JsonRpc\Exceptions\ApplicationException;
use Datto\JsonRpc\Exceptions\Exception;
use Djereg\Laravel\RabbitMQ\RPC\Server\Events\ProcedureCall;
use Djereg\Laravel\RabbitMQ\RPC\Server\Events\ProcedureCalled;
use Djereg\Laravel\RabbitMQ\RPC\Server\Events\ProcedureCalling;
use Djereg\Laravel\RabbitMQ\RPC\Server\Exceptions\MethodException;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Validation\ValidationException;
use Throwable;

readonly class Evaluator implements BaseEvaluator
{
    public function __construct(
        private Dispatcher $events,
        private Handler $handler,
    ) {
        //
    }

    /**
     * @param string $method
     * @param array $arguments
     *
     * @return mixed
     * @throws MethodException
     * @throws ApplicationException
     * @throws Throwable
     */
    public function evaluate($method, $arguments): mixed
    {
        $event = new ProcedureCall($method, $arguments);

        try {
            $this->events->dispatch(new ProcedureCalling($event));
            $result = $this->events->dispatch($event, [], true);
            $this->events->dispatch(new ProcedureCalled($event));
        } catch (ValidationException $e) {
            throw new ApplicationException($e->getMessage(), -30422, $e->errors());
        } catch (Exception $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->handler->report($e);
            throw new ApplicationException($e->getMessage(), $e->getCode());
        }

        if ($result === null) {
            throw new MethodException('Method not found');
        }

        return $result;
    }
}

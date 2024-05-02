<?php

namespace Djereg\Laravel\RabbitMQ\Services;

use Datto\JsonRpc\Evaluator as BaseEvaluator;
use Datto\JsonRpc\Exceptions\ApplicationException;
use Datto\JsonRpc\Exceptions\Exception;
use Djereg\Laravel\RabbitMQ\Exceptions\MethodException;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Validation\ValidationException;
use Throwable;

class Evaluator implements BaseEvaluator
{
    private array $handlers = [];

    public function __construct(
        private readonly ExceptionHandler $handler,
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
        if (!$handler = $this->handlers[$method] ?? null) {
            throw new MethodException('Method not found');
        }

        try {
            $result = call_user_func($handler, ...$arguments);
        } catch (ValidationException $e) {
            throw new ApplicationException($e->getMessage(), -30422, $e->errors());
        } catch (Exception $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->handler->report($e);
            throw new ApplicationException($e->getMessage(), $e->getCode());
        }

        return $result;
    }

    public function addHandler(string $method, callable $handler): void
    {
        $this->handlers[$method] = $handler;
    }
}

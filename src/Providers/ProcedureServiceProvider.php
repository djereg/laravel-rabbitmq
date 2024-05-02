<?php

namespace Djereg\Laravel\RabbitMQ\Providers;

use Djereg\Laravel\RabbitMQ\Procedures\Procedure;
use Djereg\Laravel\RabbitMQ\Services\DiscoverProcedures;
use Djereg\Laravel\RabbitMQ\Services\Evaluator;
use Illuminate\Support\Arr;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class ProcedureServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->booting(function () {
            /** @var Evaluator $evaluator */
            $evaluator = $this->app->make(Evaluator::class);

            $procedures = $this->getProcedures();

            foreach ($procedures as $name => $handlers) {
                $handlers = Arr::wrap($handlers);

                if (count($handlers) > 1) {
                    throw new RuntimeException('Multiple handlers defined for procedure name [' . $name . ']');
                }

                [$class, $method] = explode('@', $handlers[0]);
                $instance = $this->app->make($class);

                if (!is_a($instance, Procedure::class)) {
                    continue;
                }
                $evaluator->addHandler($name, [$instance, $method]);
            }
        });
    }

    public function getProcedures(): array
    {
        return array_merge_recursive(
            $this->discoveredProcedures(),
        //$this->listens()
        );
    }

    /**
     * Get the discovered procedures for the application.
     *
     * @return array
     */
    protected function discoveredProcedures(): array
    {
        return $this->shouldDiscoverProcedures()
            ? $this->discoverProcedures()
            : [];
    }

    /**
     * Determine if procedures should be automatically discovered.
     *
     * @return bool
     */
    public function shouldDiscoverProcedures(): bool
    {
        return $this->app->runningInConsole();
    }

    /**
     * Discover the events and listeners for the application.
     *
     * @return array
     */
    public function discoverProcedures(): array
    {
        return collect($this->discoverProceduresWithin())
            ->reject(function ($directory) {
                return !is_dir($directory);
            })
            ->reduce(function ($discovered, $directory) {
                return array_merge_recursive(
                    $discovered,
                    DiscoverProcedures::within(
                        $directory,
                        $this->procedureDiscoveryBasePath(),
                    ),
                );
            }, []);
    }

    /**
     * Get the listener directories that should be used to discover events.
     *
     * @return array
     */
    protected function discoverProceduresWithin(): array
    {
        return [
            $this->app->path('Procedures'),
        ];
    }

    /**
     * Get the base path to be used during event discovery.
     *
     * @return string
     */
    protected function procedureDiscoveryBasePath(): string
    {
        return base_path();
    }
}

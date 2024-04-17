<?php

namespace Djereg\Laravel\RabbitMQ\Core\Providers;

use Djereg\Laravel\RabbitMQ\Core\Commands\ConsumeCommand;
use Djereg\Laravel\RabbitMQ\Core\Services\Consumer;
use Djereg\Laravel\RabbitMQ\Event\Providers\EventServiceProvider;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Queue\QueueManager;
use Illuminate\Support\ServiceProvider;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Connectors\RabbitMQConnector;

class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerConfig();

        $this->app->singleton('rabbitmq.consumer', function () {
            $isDownForMaintenance = function () {
                return $this->app->isDownForMaintenance();
            };

            return new Consumer(
                manager: $this->app['queue'],
                events: $this->app['events'],
                exceptions: $this->app[ExceptionHandler::class],
                isDownForMaintenance: $isDownForMaintenance,
            );
        });

        $this->app->singleton(ConsumeCommand::class, static function ($app) {
            return new ConsumeCommand(
                worker: $app['rabbitmq.consumer'],
                cache: $app['cache.store'],
            );
        });

        $this->commands([
            ConsumeCommand::class,
        ]);
    }

    public function boot(): void
    {
        /** @var QueueManager $queue */
        $queue = $this->app['queue'];

        $queue->addConnector('rabbitmq', function () {
            return new RabbitMQConnector($this->app['events']);
        });
    }

    public function provides(): array
    {
        return [
            'rabbitmq.consumer',
            ConsumeCommand::class,
        ];
    }

    private function registerConfig(): void
    {
        $file = __DIR__ . '/../../../config/rabbitmq.php';
        $this->mergeConfigFrom($file, 'rabbitmq');
        $this->mergeConfigFrom($file, 'queue.connections.rabbitmq');
    }
}

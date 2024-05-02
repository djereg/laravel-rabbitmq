<?php

namespace Djereg\Laravel\RabbitMQ\Providers;

use Datto\JsonRpc\Server;
use Djereg\Laravel\RabbitMQ\Commands\ConsumeCommand;
use Djereg\Laravel\RabbitMQ\Contracts\PublishEventInterface;
use Djereg\Laravel\RabbitMQ\Events\MessageReceived;
use Djereg\Laravel\RabbitMQ\Listeners\ProcessEventMessage;
use Djereg\Laravel\RabbitMQ\Listeners\ProcessRequestMessage;
use Djereg\Laravel\RabbitMQ\Listeners\PublishEventMessage;
use Djereg\Laravel\RabbitMQ\Services\Consumer;
use Djereg\Laravel\RabbitMQ\Services\Evaluator;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Queue\QueueManager;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Connectors\RabbitMQConnector;

class RabbitMQServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerConfig();

        $this->app->singleton('rabbitmq.consumer', function ($app) {
            $isDownForMaintenance = function () {
                return $this->app->isDownForMaintenance();
            };

            return new Consumer(
                manager: $app['queue'],
                events: $app['events'],
                exceptions: $app[ExceptionHandler::class],
                isDownForMaintenance: $isDownForMaintenance,
            );
        });

        $this->app->singleton(ConsumeCommand::class, static function ($app) {
            return new ConsumeCommand(
                worker: $app['rabbitmq.consumer'],
                cache: $app['cache.store'],
            );
        });

        $this->app->singleton(Evaluator::class, function ($app) {
            return new Evaluator($app[ExceptionHandler::class]);
        });
        $this->app->singleton(Server::class, function ($app) {
            return new Server($app[Evaluator::class]);
        });

        $this->app->singleton(PublishEventMessage::class, function ($app) {
            return new PublishEventMessage($app['queue.connection']);
        });
        $this->app->singleton(ProcessEventMessage::class, function ($app) {
            return new ProcessEventMessage($app['events']);
        });
        $this->app->singleton(ProcessRequestMessage::class, function ($app) {
            return new ProcessRequestMessage($app['queue.connection'], $app[Server::class]);
        });

        Event::listen(PublishEventInterface::class, PublishEventMessage::class);

        Event::listen(MessageReceived::class, ProcessEventMessage::class);
        Event::listen(MessageReceived::class, ProcessRequestMessage::class);

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
            Evaluator::class,
            Server::class,
            PublishEventMessage::class,
            ProcessEventMessage::class,
            ProcessRequestMessage::class,
        ];
    }

    private function registerConfig(): void
    {
        $file = __DIR__ . '/../../config/rabbitmq.php';
        $this->mergeConfigFrom($file, 'rabbitmq');
        $this->mergeConfigFrom($file, 'queue.connections.rabbitmq');
    }
}

<?php

namespace Djereg\Laravel\RabbitMQ\RPC\Server\Providers;

use Datto\JsonRpc\Server;
use Djereg\Laravel\RabbitMQ\Core\Events\MessageReceived;
use Djereg\Laravel\RabbitMQ\RPC\Server\Events\ProcedureEvent;
use Djereg\Laravel\RabbitMQ\RPC\Server\Listeners\ProcessMessage;
use Djereg\Laravel\RabbitMQ\RPC\Server\Listeners\ProcedureCall;
use Djereg\Laravel\RabbitMQ\RPC\Server\Services\Evaluator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServerServiceProvider extends BaseServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Server::class, function ($app) {
            return new Server($app->make(Evaluator::class));
        });
        $this->app->singleton(ProcedureCall::class, function ($app) {
            return new ProcedureCall($app[Server::class], $app['queue.connection']);
        });

        Event::listen(MessageReceived::class, ProcessMessage::class);
        Event::listen(ProcedureEvent::class, ProcedureCall::class);
    }
}

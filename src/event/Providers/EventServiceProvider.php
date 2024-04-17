<?php

namespace Djereg\Laravel\RabbitMQ\Event\Providers;

use Djereg\Laravel\RabbitMQ\Core\Events\MessageReceived;
use Djereg\Laravel\RabbitMQ\Event\Contracts\PublishEvent;
use Djereg\Laravel\RabbitMQ\Event\Events\RegisterBindingKeys as RegisterBindingKeysEvent;
use Djereg\Laravel\RabbitMQ\Event\Listeners\ProcessMessage;
use Djereg\Laravel\RabbitMQ\Event\Listeners\PublishMessage;
use Djereg\Laravel\RabbitMQ\Event\Listeners\RegisterBindingKeys;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RegisterBindingKeys::class, function ($app) {
            return new RegisterBindingKeys($app['config'], $app['queue.connection']);
        });
        $this->app->singleton(PublishMessage::class, function ($app) {
            return new PublishMessage($app['config'], $app['queue.connection']);
        });

        Event::listen(PublishEvent::class, PublishMessage::class);
        Event::listen(MessageReceived::class, ProcessMessage::class);
        Event::listen(RegisterBindingKeysEvent::class, RegisterBindingKeys::class);
    }
}

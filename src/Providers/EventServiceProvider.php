<?php

namespace Djereg\Laravel\RabbitMQ\Providers;

use Djereg\Laravel\RabbitMQ\Listeners\MessageEventListener;
use Djereg\Laravel\RabbitMQ\Services\Consumer;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        parent::register();

        $this->booting(function () {

            /** @var Consumer $consumer */
            $consumer = $this->app->make('rabbitmq.consumer');

            $events = $this->getEvents();

            foreach ($events as $listeners) {
                $listeners = array_map(fn($x) => explode('@', $x)[0], $listeners);
                $listeners = array_unique($listeners, SORT_REGULAR);

                foreach ($listeners as $listener) {
                    $instance = $this->app->make($listener);

                    if (!is_a($instance, MessageEventListener::class)) {
                        continue;
                    }

                    $listen = $instance::$listen ?? [];
                    $consumer->addRoutingKeys($listen);
                }
            }
        });
    }

    public function shouldDiscoverEvents(): bool
    {
        return true;
    }
}

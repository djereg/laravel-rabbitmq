<?php

namespace Djereg\Laravel\RabbitMQ\Event\Listeners;

use Djereg\Laravel\RabbitMQ\Event\Events\RegisterBindingKeys as Event;
use Illuminate\Contracts\Config\Repository as Config;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\RabbitMQQueue;

readonly class RegisterBindingKeys
{
    public function __construct(
        private Config $config,
        private RabbitMQQueue $queue,
    ) {
        //
    }

    public function handle(Event $event): void
    {
        $c = $this->config->get('rabbitmq');

        $channel = $this->queue->getChannel();
        foreach ($event->keys as $key) {
            $channel->queue_bind($c['queue'], $c['options']['exchange'], $key);
        }
    }
}

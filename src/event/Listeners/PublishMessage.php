<?php

namespace Djereg\Laravel\RabbitMQ\Event\Listeners;

use Djereg\Laravel\RabbitMQ\Core\Events\MessagePublished;
use Djereg\Laravel\RabbitMQ\Core\Events\MessagePublishing;
use Djereg\Laravel\RabbitMQ\Event\Contracts\PublishEvent;
use Illuminate\Config\Repository as Config;
use PhpAmqpLib\Message\AMQPMessage;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\RabbitMQQueue;

readonly class PublishMessage
{
    public function __construct(
        private Config $config,
        private RabbitMQQueue $queue,
    ) {
        //
    }

    public function handle(PublishEvent $event): void
    {
        $message = new AMQPMessage(
            body: json_encode($event->payload()),
            properties: [
                'content_type'        => 'application/json',
                'delivery_mode'       => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                'application_headers' => [
                    'X-Message-Type' => ['S', 'event'],
                    'X-Event-Name'   => ['S', $event->event()],
                ],
            ]
        );

        if ($queue = $event->queue()) {
            $this->publish($message, '', $queue);
        } else {
            $exchange = $this->config->get('rabbitmq.options.exchange');
            $this->publish($message, $exchange, $event->event());
        }
    }

    private function publish(AMQPMessage $message, string $exchange, string $routingKey): void
    {
        $channel = $this->queue->getChannel();

        event(new MessagePublishing($message, $exchange, $routingKey));
        $channel->basic_publish($message, $exchange, $routingKey);
        event(new MessagePublished($message, $exchange, $routingKey));
    }
}

<?php

namespace Djereg\Laravel\RabbitMQ\Core\Services;

use Djereg\Laravel\RabbitMQ\Core\Events\MessagePublished;
use Djereg\Laravel\RabbitMQ\Core\Events\MessagePublishing;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Events\Dispatcher;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Exception\AMQPChannelClosedException;
use PhpAmqpLib\Exception\AMQPConnectionBlockedException;
use PhpAmqpLib\Exception\AMQPConnectionClosedException;
use PhpAmqpLib\Message\AMQPMessage;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\RabbitMQQueue;

readonly class Publisher
{
    public function __construct(
        private Config $config,
        private Dispatcher $events,
        private RabbitMQQueue $queue,
    ) {
        //
    }

//    public function event(PublishEvent $event): void
//    {
//        $message = new AMQPMessage(
//            body: json_encode($event->payload()),
//            properties: [
//                'content_type'        => 'application/json',
//                'delivery_mode'       => AMQPMessage::DELIVERY_MODE_PERSISTENT,
//                'application_headers' => [
//                    'erp_message_type' => ['S', 'event'],
//                    'erp_event_name'   => ['S', $event->event()],
//                ],
//            ]
//        );
//
//        if ($queue = $event->queue()) {
//            $this->publish($message, '', $queue);
//        } else {
//            $exchange = $this->config->get('options.exchange');
//            $this->publish($message, $exchange, $event->event());
//        }
//    }

    public function channel(bool $new = false): AMQPChannel
    {
        if ($new) {
            return $this->queue->getConnection()->channel();
        } else {
            return $this->queue->getChannel();
        }
    }

    /**
     * Publish message to the default exchange with the specified routing key.
     *
     * @param string $topic The routing key called as topic.
     * @param AMQPMessage $message
     *
     * @return void
     */
    public function topic(string $topic, AMQPMessage $message, AMQPChannel $channel = null): void
    {
        $exchange = $this->config->get('options.exchange');
        $this->publish($message, $exchange, $topic, $channel);
    }

    /**
     * Publish message directly to the specified queue.
     *
     * @param string $queue The queue name where the message will be published.
     * @param AMQPMessage $message
     *
     * @return void
     */
    public function direct(string $queue, AMQPMessage $message, AMQPChannel $channel = null): void
    {
        $this->publish($message, '', $queue, $channel);
    }

    /**
     * @param AMQPMessage $message
     * @param string $exchange
     * @param string $routingKey
     *
     * @return void
     * @throws AMQPChannelClosedException
     * @throws AMQPConnectionClosedException
     * @throws AMQPConnectionBlockedException
     */
    public function publish(AMQPMessage $message, string $exchange, string $routingKey, AMQPChannel $channel = null): void
    {
        $this->events->dispatch(new MessagePublishing($message, $exchange, $routingKey));
        ($channel ?? $this->channel())->basic_publish($message, $exchange, $routingKey);
        $this->events->dispatch(new MessagePublished($message, $exchange, $routingKey));
    }
}

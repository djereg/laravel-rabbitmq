<?php

namespace Djereg\Laravel\RabbitMQ\Queues;

use Djereg\Laravel\RabbitMQ\Events\MessagePublished;
use Djereg\Laravel\RabbitMQ\Events\MessagePublishing;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\RabbitMQQueue as Queue;

class RabbitMQQueue extends Queue
{
    public function wrap($job): string
    {
        return $this->createPayload($job, $this->getQueue());
    }

    public function getWaitTimeout(float $maximumPoll = 10.0): int
    {
        $timeout = $this->connection->getReadTimeout();
        $heartBeat = $this->connection->getHeartbeat();
        if ($heartBeat > 2) {
            $timeout = min($timeout, floor($heartBeat / 2));
        }
        return max(min($timeout, $maximumPoll), 1);
    }

    public function setup(array $routingKeys): void
    {
        $this->initQueue();
        $this->initExchange();
        $this->bindRoutingKeys($routingKeys);
    }

    protected function initExchange(): void
    {
        $name = $this->getExchange();
        $type = $this->getExchangeType();

        $this->declareExchange($name, $type);
    }

    protected function initQueue(): void
    {
        $name = $this->getQueue();

        $this->declareQueue($name);
    }

    public function bindRoutingKeys(array $keys): void
    {
        $queue = $this->getQueue();
        $channel = $this->getChannel();
        $exchange = $this->getExchange();

        $channel->queue_bind($queue, $exchange, $queue);

        foreach ($keys as $key) {
            $channel->queue_bind($queue, $exchange, $key);
        }
    }

    public function publishMessage(AMQPMessage $message, string $routingKey, string $exchange = null, AMQPChannel $channel = null): void
    {
        $channel ??= $this->getChannel();
        $exchange ??= $this->getExchange();

        event(new MessagePublishing($message, $exchange, $routingKey));
        $channel->basic_publish($message, $exchange, $routingKey);
        event(new MessagePublished($message, $exchange, $routingKey));
    }
}

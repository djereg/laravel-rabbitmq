<?php

namespace Djereg\Laravel\RabbitMQ\Jobs;

use Djereg\Laravel\RabbitMQ\Queues\RabbitMQQueue;
use Illuminate\Support\Arr;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Jobs\RabbitMQJob as BaseJob;

/**
 * @property RabbitMQQueue $rabbitmq
 */
class RabbitMQJob extends BaseJob
{
    private string $rawBody;

    public function getRawBody(): string
    {
        if (isset($this->rawBody)) {
            return $this->rawBody;
        }

        $body = parent::getRawBody();

        $headers = $this->getRabbitMQMessageHeaders() ?? [];

        $type = $headers['X-Message-Type'] ?? null;

        if (!$type) {
            return $this->rawBody = $body;
        }

        $properties = $this->message->get_properties();
        $properties = Arr::except($properties, ['application_headers']);

        $job = new MessageJob($body, $headers, $properties);

        return $this->rawBody = $this->rabbitmq->wrap($job);
    }
}

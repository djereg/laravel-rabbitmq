<?php

namespace Djereg\Laravel\RabbitMQ\Listeners;

use Djereg\Laravel\RabbitMQ\Events\MessageProcessed;
use Djereg\Laravel\RabbitMQ\Events\MessageProcessing;
use Djereg\Laravel\RabbitMQ\Events\MessageReceived;
use Djereg\Laravel\RabbitMQ\Services\ArrayBag;
use RuntimeException;

abstract class MessageHandler
{
    protected bool $stopPropagation = true;

    public function handle(MessageReceived $event): ?false
    {
        $type = $event->headers->get('X-Message-Type');

        if (!$this->shouldHandle($type)) {
            return null;
        }

        event(new MessageProcessing($event));
        $this->handleMessage($event);
        event(new MessageProcessed($event));

        return !$this->stopPropagation;
    }

    abstract protected function shouldHandle(string $type): bool;

    abstract protected function handleMessage(MessageReceived $event): void;

    protected function getDecodedBody(MessageReceived $event): ArrayBag
    {
        if (!$contentType = $event->headers->get('Content-Type')) {
            throw new RuntimeException('Content-Type header is missing');
        }
        if ($contentType !== 'application/json') {
            throw new RuntimeException('Unsupported Content-Type: ' . $contentType);
        }
        return new ArrayBag(json_decode($event->body, true));
    }
}

<?php

namespace Djereg\Laravel\RabbitMQ\Event\Listeners;

use Djereg\Laravel\RabbitMQ\Core\Events\MessageProcessed;
use Djereg\Laravel\RabbitMQ\Core\Events\MessageProcessing;
use Djereg\Laravel\RabbitMQ\Core\Events\MessageReceived;
use Djereg\Laravel\RabbitMQ\Core\Services\ArrayBag;
use Djereg\Laravel\RabbitMQ\Event\Events\MessageQueueEvent;
use ErrorException;
use Illuminate\Contracts\Events\Dispatcher;
use JsonException;
use PhpAmqpLib\Message\AMQPMessage;

readonly class ProcessMessage
{
    public function __construct(
        private Dispatcher $events,
    ) {
        //
    }

    /**
     * @param MessageReceived $event
     *
     * @throws JsonException
     * @throws ErrorException
     */
    public function handle(MessageReceived $event): void
    {
        $type = $event->headers->get('X-Message-Type');

        if ($type !== 'event') {
            return;
        }

        $this->events->dispatch(new MessageProcessing($event));
        $this->handleMessage($event->headers, $event->message);
        $this->events->dispatch(new MessageProcessed($event));
    }

    /**
     * @param ArrayBag $headers
     * @param AMQPMessage $message
     *
     * @return void
     * @throws ErrorException
     * @throws JsonException
     */
    private function handleMessage(ArrayBag $headers, AMQPMessage $message): void
    {
        $event = $headers->get('X-Event-Name');
        $payload = $this->getMessagePayload($message);

        $this->events->dispatch(
            new MessageQueueEvent($event, $headers, $payload)
        );
    }

    /**
     * @param AMQPMessage $message
     *
     * @return ArrayBag
     * @throws JsonException
     * @throws ErrorException
     */
    private function getMessagePayload(AMQPMessage $message): ArrayBag
    {
//        $contentType = $message->get('content_type');
//
//        if ($contentType === 'application/json') {
            return new ArrayBag(
                json_decode($message->body, true, 512, JSON_THROW_ON_ERROR)
            );
//        }
//
//        throw new ErrorException('Unsupported content type: ' . $contentType);
    }
}

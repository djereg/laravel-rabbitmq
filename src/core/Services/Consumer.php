<?php

namespace Djereg\Laravel\RabbitMQ\Core\Services;

use Djereg\Laravel\RabbitMQ\Core\Events\MessageReceived;
use Djereg\Laravel\RabbitMQ\Event\Events\RegisterListenerKeys;
use Illuminate\Queue\WorkerOptions;
use PhpAmqpLib\Exception\AMQPRuntimeException;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Message\AMQPMessage;
use Throwable;
use VladimirYuldashev\LaravelQueueRabbitMQ\Consumer as BaseConsumer;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\RabbitMQQueue;

class Consumer extends BaseConsumer
{
    public function daemon($connectionName, $queue, WorkerOptions $options): int
    {
        if ($this->supportsAsyncSignals()) {
            $this->listenForSignals();
        }

        $lastRestart = $this->getTimestampOfLastQueueRestart();

        [$startTime, $jobsProcessed] = [hrtime(true) / 1e9, 0];

        /** @var RabbitMQQueue $connection */
        $connection = $this->manager->connection($connectionName);

        $this->channel = $connection->getChannel();

        $this->initQueue($queue);
        $this->bindRoutingKeys();

        $this->channel->basic_qos(
            $this->prefetchSize,
            $this->prefetchCount,
            false
        );

        $jobClass = $connection->getJobClass();
        $arguments = [];
        if ($this->maxPriority) {
            $arguments['priority'] = ['I', $this->maxPriority];
        }

        $this->channel->basic_consume(
            queue: $queue,
            consumer_tag: $this->consumerTag,
            no_local: false,
            no_ack: false,
            exclusive: false,
            nowait: false,
            callback: function (AMQPMessage $message) use ($connection, $options, $connectionName, $queue, $jobClass, &$jobsProcessed): void {

                try {
                    $headers = $this->getMessageHeaders($message);
                    if ($headers->has('X-Message-Type')) {
                        $this->events->dispatch(
                            new MessageReceived($headers, $message)
                        );
                        $message->ack();
                        return;
                    }
                } catch (Throwable $e) {
                    $message->reject(false);
                    return;
                }

                $job = new $jobClass(
                    $this->container,
                    $connection,
                    $message,
                    $connectionName,
                    $queue
                );

                dump('set job');
                $this->currentJob = $job;

                if ($this->supportsAsyncSignals()) {
                    $this->registerTimeoutHandler($job, $options);
                }

                $jobsProcessed++;

                dump('running job');
                $this->runJob($job, $connectionName, $options);

                if ($this->supportsAsyncSignals()) {
                    $this->resetTimeoutHandler();
                }

//                if ($options->rest > 0) {
//                    $this->sleep($options->rest);
//                }
            },
            ticket: null,
            arguments: $arguments
        );

        while ($this->channel->is_consuming()) {

            // Before reserving any jobs, we will make sure this queue is not paused and
            // if it is we will just pause this worker for a given amount of time and
            // make sure we do not need to kill this worker process off completely.
            if (!$this->daemonShouldRun($options, $connectionName, $queue)) {
                $this->pauseWorker($options, $lastRestart);
                continue;
            }

            // If the daemon should run (not in maintenance mode, etc.), then we can wait for a job.
            try {
                dump('waiting');
                $this->channel->wait(null, false);
            } catch (AMQPTimeoutException $e) {
                // If timeout exception is thrown, we will just restart the wait loop.
            } catch (AMQPRuntimeException $e) {
                $this->exceptions->report($e);
                $this->kill(self::EXIT_ERROR, $options);
            } catch (Throwable $e) {
                $this->exceptions->report($e);
                $this->stopWorkerIfLostConnection($e);
            }

            // If no job is got off the queue, we will need to sleep the worker.
//            if ($this->currentJob === null) {
//                $this->sleep($options->sleep);
//            }

            // Finally, we will check to see if we have exceeded our memory limits or if
            // the queue should restart based on other indications. If so, we'll stop
            // this worker and let whatever is "monitoring" it restart the process.
            $status = $this->stopIfNecessary(
                $options,
                $lastRestart,
                $startTime,
                $jobsProcessed,
                $this->currentJob
            );

            if (!is_null($status)) {
                return $this->stop($status, $options);
            }

            dump('clear job');
            $this->currentJob = null;
        }

        return 0;
    }

    private function getMessageHeaders(AMQPMessage $message): ArrayBag
    {
        return new ArrayBag($message->get('application_headers')->getNativeData());
    }

    private function initQueue(string $queue): void
    {
        $this->channel->queue_declare(
            queue: $queue,
            durable: true,
            auto_delete: false,
        );
    }

    private function bindRoutingKeys(): void
    {
        $this->events->dispatch(new RegisterListenerKeys());
    }

    protected function listenForSignals(): void
    {
        pcntl_async_signals(true);

        $handler = function () {

            // If there's no job running, it's safe to quit.
            if (!$this->currentJob) {
                $this->kill();

                $this->channel->stopConsume();
            }

            // If there's a job running,
            // set a flag to quit after the job is done.
            $this->shouldQuit = true;
        };

        pcntl_signal(SIGQUIT, $handler);
        pcntl_signal(SIGTERM, $handler);
        pcntl_signal(SIGUSR1, $handler);
        pcntl_signal(SIGHUP, $handler);
    }
}

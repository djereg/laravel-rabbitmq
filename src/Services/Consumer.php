<?php

namespace Djereg\Laravel\RabbitMQ\Services;

use Djereg\Laravel\RabbitMQ\Queues\RabbitMQQueue as Queue;
use Illuminate\Queue\WorkerOptions;
use PhpAmqpLib\Exception\AMQPRuntimeException;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Message\AMQPMessage as Message;
use Throwable;
use VladimirYuldashev\LaravelQueueRabbitMQ\Consumer as BaseConsumer;

class Consumer extends BaseConsumer
{
    private array $routingKeys = [];

    public function daemon($connectionName, $queue, WorkerOptions $options): int
    {
        if ($supportsAsyncSignals = $this->supportsAsyncSignals()) {
            $this->listenForSignals();
        }

        $lastRestart = $this->getTimestampOfLastQueueRestart();

        [$startTime, $jobsProcessed] = [hrtime(true) / 1e9, 0];

        /** @var Queue $connection */
        $connection = $this->manager->connection($connectionName);

        $connection->setup($this->routingKeys);

        $this->channel = $connection->getChannel();

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

        $handler = function (Message $message) use ($connection, $options, $connectionName, $queue, $jobClass, $supportsAsyncSignals, &$jobsProcessed): void {

            if (isset($this->resetScope)) {
                ($this->resetScope)();
            }

            $job = new $jobClass($this->container, $connection, $message, $connectionName, $queue);

            $this->currentJob = $job;

            if ($supportsAsyncSignals) {
                $this->registerTimeoutHandler($job, $options);
            }

            $jobsProcessed++;

            $this->runJob($job, $connectionName, $options);

            if ($supportsAsyncSignals) {
                $this->resetTimeoutHandler();
            }
        };

        $this->channel->basic_consume(
            queue: $queue,
            consumer_tag: $this->consumerTag,
            callback: $handler,
            arguments: $arguments
        );

        $timeout = $connection->getWaitTimeout(10);

        while ($this->channel->is_consuming() || $this->channel->hasPendingMethods()) {

            // Before reserving any jobs, we will make sure this queue is not paused and
            // if it is we will just pause this worker for a given amount of time and
            // make sure we do not need to kill this worker process off completely.
            if (!$this->daemonShouldRun($options, $connectionName, $queue)) {
                $status = $this->pauseWorker($options, $lastRestart);

                if (!is_null($status)) {
                    return $this->stop($status, $options);
                }

                continue;
            }

            // If the daemon should run (not in maintenance mode, etc.), then we can wait for a job.
            try {
                $this->channel->wait(null, false, $timeout);
            } catch (AMQPTimeoutException $e) {
                // Something might be wrong, try to send heartbeat which involves select+write
                $this->channel->getConnection()->checkHeartBeat();
            } catch (AMQPRuntimeException $e) {
                // Report the exception and stop the worker.
                $this->exceptions->report($e);
                $this->kill(self::EXIT_ERROR, $options);
            } catch (Throwable $e) {
                // Report the exception and stop the worker if any connection error occurred.
                $this->exceptions->report($e);
                $this->stopWorkerIfLostConnection($e);
            }

            // Finally, we will check to see if we have exceeded our memory limits or if
            // the queue should restart based on other indications. If so, we'll stop
            // this worker and let whatever is "monitoring" it restart the process.
            $status = $this->stopIfNecessary(
                $options, $lastRestart, $startTime, $jobsProcessed, $this->currentJob
            );

            if (!is_null($status)) {
                return $this->stop($status, $options);
            }

            $this->currentJob = null;
        }

        return 0;
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

    public function addRoutingKeys(array $keys): void
    {
        foreach ($keys as $key) {
            if (in_array($key, $this->routingKeys, true)) {
                continue;
            }
            $this->routingKeys[] = $key;
        }
    }
}

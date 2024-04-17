# First of all

**This package is primarily intended for internal, private use in own projects. If it meets your needs, feel free to use it, but in case of any modification requests, I will consider my own needs first.**

# Laravel RabbitMQ

## Table of Contents

- [Description](#description)
- [Motivation](#motivation)
- [Usage](#usage)
  - [Installation](#installation)
  - [Configuration](#configuration)
- [Events](#events)
  - [Dispatching events](#dispatching-events)
  - [Listening for events](#listening-for-events)
  - [How the events are processed?](#how-the-events-are-processed)
- [RPC](#rpc)
  - [Calling remote procedures](#calling-remote-procedures)
  - [Handling remote procedure calls](#handling-remote-procedure-calls)
  - [How the procedure calls are processed?](#how-the-procedure-calls-are-processed)

## Description

This package is an intermediate layer between RabbitMQ and Laravel Queue.

The package is based on [vladimir-yuldashev/laravel-queue-rabbitmq](https://github.com/vyuldashev/laravel-queue-rabbitmq) package, which adds RabbitMQ as a queue driver to Laravel.

This package extends the functionality of the original package by adding the ability to send and receive events and RPC calls through RabbitMQ messages.

## Motivation

Since the microservice architecture has become very popular, I needed a library that provides the possibility of communicating with services written in different programming languages or frameworks.

Laravel has a powerful queue system, but it is a closed Laravel specific system. This package allows you to communicate through messages between Laravel and/or other non-Laravel microservices.

On the top of simple JSON messages, utilizes the Laravel Queue system, which perfectly does the rest of the job.

## Usage

### Installation

You can install this package via composer using this command:

```bash
composer require djereg/laravel-rabbitmq
```

The package will automatically register itself.

### Configuration

The configuration is done through environment variables.

```dotenv
# Set the queue connection to rabbitmq
QUEUE_CONNECTION=rabbitmq

RABBITMQ_HOST=rabbitmq
RABBITMQ_PORT=5672
RABBITMQ_USER=guest
RABBITMQ_PASSWORD=guest
RABBITMQ_VHOST=/
RABBITMQ_QUEUE=queue-name
RABBITMQ_EXCHANGE=exchange-name
```

## Events

Provides an event based asynchronous communication between services.

### Dispatching events

Create an event class that extends the `MessagePublishEvent` class.

```php
use Djereg\Laravel\RabbitMQ\Event\Events\MessagePublishEvent;

class UserCreated extends MessagePublishEvent
{
    // Set the event name
    protected string $event = 'user.created';

    public function __construct(private User $user)
    {
        $this->user = $user;
    }

    // Create a payload method that returns the data to be sent
    public function payload(): array
    {
        return [
            'user_id' => $this->user->id,
        ];
    }
}
```

And after just dispatch the event like any other Laravel event.

```php
event(new UserCreated($user));
```

### Listening for events

Create an event listener class that extends the `MessageQueueListener` class.

The working mechanism is a little bit different from the Laravel event listeners.
First, you have to specify the events you want to listen to in the `$listen` property.
Next, instead of public `handle()` method, you have to define the `onEvent()` method.
This is because the `handle()` method is already used under the hood by the parent class.

```php
class NotifyUser extends MessageQueueListener {

    // Specify the events you want to listen to.
    // You can listen to multiple events by adding them to the array.
    protected array $listen = [
        'user.created',

        // 'user.updated',
        // 'user.deleted',
        // etc
    ];

    // The method that will be called when the event is received.
    // The event object is passed as an argument containing the event name and payload.
    protected function onEvent(MessageQueueEvent $event): void {

    }

    // You can also define separate methods for each event.
    // The method name must be in the format on{EventName} where {EventName}
    // is the StudlyCase format of the event defined in the $listen property.
    // When both methods are defined, the on{EventName} method will be called.
    protected function onUserCreated(MessageQueueEvent $event): void {
        //
    }

    // If no on{EventName} or onEvent method is defined, an exception will be thrown.
}
```

### How the events are processed?

When an event-type message is received, the matching listener will be queued by default, and will be processed by the Laravel queue worker in the same way as other queued jobs.

This means that the received event will be processed in two iterations. First, the message will be received and queued, and then the listener will be processed by the Laravel queue worker.

## RPC

Works very similarly to events, but with the difference that the matching listener can and must return a response.

Uses the [JSON-RPC 2.0](https://www.jsonrpc.org/specification) protocol for communication.

### Calling remote procedures

First of all you have to set up a client and inject it into the service where you want to use it. The best way is to do it in a service provider.

```php
use Djereg\Laravel\RabbitMQ\RPC\Client\Services\Client;

class AppServiceProvider extends ServiceProvider {

    public function register(): void
    {
        $this->app->singleton(UserService::class, function($app) {

            $remoteServiceName = 'users';

            // Instantiate the client with the remote service name and the queue connection
            $client = new Client($remoteServiceName, $app['queue.connection'])

            // Inject the client into the service
            return new UserService($client);
        })
    }
}
```

Then you can use the client in the service.

```php
use Djereg\Laravel\RabbitMQ\RPC\Client\Services\Client;

class UserService {

    public function __construct(private Client $client) {}

    public function getUser(int $id): User
    {
        // Call the remote procedure
        $user = $this->client->call('get', ['id' => $id]);

        // Return the user model (for example)
        return new User($user);
    }
}
```

### Handling remote procedure calls

Create a listener class that extends the `Procedure` class.

Works very similarly to the event listeners described above.
The `handle()` method is used under the hood by the parent class, so you have to define the `__invoke()` method.
This method will be called when the procedure is called.

```php
use Djereg\Laravel\RabbitMQ\RPC\Server\Listeners\Procedure;

class GetUser extends Procedure {

    // Set the procedure name that will be called by the client
    protected string $method = 'get';

    public function __invoke(int $id): array
    {
        $user = User::find($id);

        return $user->toArray();
    }
}
```

You have to return any basic PHP type or array, except `null`. It should be fixed in the future,
but not today. The short story is that since the procedures are listeners, the event dispatcher
expects a non-null return value to decide whether the procedure was found and executed successfully.
This has a small performance impact, but it is negligible.

### How the procedure calls are processed?

Almost the same [as events](#how-the-events-are-processed).

The procedure-type message puts a handler job to the queue and Laravel does the rest of the job.

## Known Issues

- The RPC part is not optimized, but works well for small projects. Should be totally rewritten.
- NO TESTS! I know, I know. I will write them soon.

## License

[MIT licensed](LICENSE)

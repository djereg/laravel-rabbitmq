# Laravel RabbitMQ

> THIS PACKAGE IS PRIMARILY INTENDED FOR INTERNAL/PRIVATE USE IN OWN PROJECTS. IF IT MEETS YOUR NEEDS, FEEL FREE TO USE
> IT, BUT IN CASE OF ANY MODIFICATION REQUESTS, I WILL CONSIDER MY OWN NEEDS FIRST.

# Table of Contents

- [Description](#description)
- [Motivation](#motivation)
- [Usage](#usage)
    - [Installation](#installation)
    - [Configuration](#configuration)
    - [Starting the consumer](#starting-the-consumer)
- [Events](#events)
    - [Dispatching events](#dispatching-events)
    - [Listening to events](#listening-to-events)
    - [Errors in event listeners](#errors-in-event-listeners)
    - [Processing events asynchronously](#processing-events-asynchronously)
    - [Subscribing to events](#subscribing-to-events)
- [RPC](#rpc)
    - [Registering clients](#registering-clients)
    - [Calling remote procedures](#calling-remote-procedures)
    - [Registering remote procedures](#registering-remote-procedures)
    - [Handling procedure calls](#handling-procedure-calls)
    - [How the procedure calls are processed?](#how-the-procedure-calls-are-processed)
- [Laravel Queue](#laravel-queue)
- [Lifecycle Events](#lifecycle-events)
    - [MessagePublishing](#messagepublishing)
    - [MessagePublished](#messagepublished)
    - [MessageReceived](#messagereceived)
    - [MessageProcessing](#messageprocessing)
    - [MessageProcessed](#messageprocessed)
- [Known Issues](#known-issues)
- [License](#license)

# Description

This package is an intermediate layer between RabbitMQ and Laravel Queue.

The package is based
on [vladimir-yuldashev/laravel-queue-rabbitmq](https://github.com/vyuldashev/laravel-queue-rabbitmq) package, which adds
RabbitMQ as a queue driver to Laravel.

This package extends the functionality of the original package by adding the ability to send and receive events and RPC
calls through RabbitMQ messages.

# Motivation

Since the microservice architecture has become very popular, I needed a library that provides the possibility of
communicating with services written in different programming languages or frameworks.

Laravel has a powerful queue system, but it is a closed Laravel-only system. This package allows you to communicate
through messages between Laravel and/or other non-Laravel microservices.

On the top of simple JSON messages, utilizes the Laravel [Queue](https://laravel.com/docs/11.x/queues)
and [Event](https://laravel.com/docs/11.x/events) system, which perfectly does the rest of the job.

# Usage

## Installation

You can install this package via composer using this command:

```bash
composer require djereg/laravel-rabbitmq
```

The package will automatically register itself.

## Configuration

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
RABBITMQ_EXCHANGE_NAME=exchange-name
RABBITMQ_EXCHANGE_TYPE=direct
```

## Starting the consumer

To start the consumer, just run the following command:

```bash
php artisan rabbitmq:consume
```

# Events

Provides an event based asynchronous communication between services.

## Dispatching events

Create an event class that extends `MessagePublishEvent` provided by this package.

```php
# app/Events/UserCreated.php

namespace App\Events;

use Djereg\Laravel\RabbitMQ\Events\MessagePublishEvent;

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

## Listening to events

Create an event listener that extends `MessageEventListener` provided by this package.

The working mechanism is a little bit different from the Laravel event listeners.
First, you have to specify the events you want to listen to in the `$listen` property.
Next, instead of public `handle()` method, you have to define the `onEvent()` method.
This is because the `handle()` method is already used under the hood by the base `MessageEventListener` class.

```php
# app/Listeners/NotifyUser.php

namespace App\Listeners;

use Djereg\Laravel\RabbitMQ\Listeners\MessageEventListener;

class NotifyUser extends MessageEventListener {

    // Specify the events you want to listen to.
    // You can listen to multiple events by adding them to the array.
    public static array $listen = [
        'user.created',

        // 'user.updated',
        // 'user.deleted',
        // etc
    ];

    // The method that will be called when the event is received.
    // The event object is passed as an argument containing the event name and payload.
    protected function onEvent(MessageEvent $event): void {

    }

    // You can also define separate methods for each event.
    // The method name must be in the format on{EventName} where {EventName}
    // is the StudlyCase format of the event defined in the $listen property.
    // When both methods are defined, the on{EventName} method will be called.
    protected function onUserCreated(MessageEvent $event): void {
        //
    }

    // If no on{EventName} or onEvent method is defined, an exception will be thrown.
}
```

## Errors in event listeners

Since the event listeners are processed synchronously by default, if an error occurs, the job will fail and will be
retried by the retry mechanism, if it is enabled and configured.

If multiple listeners listening to the same event, the processing will stop at the first listener that throws an error
and the rest of the listeners will not be processed.

You have multiple options to prevent this behavior:

- Run the listener in the try-catch block and handle the error in the listener.
- Put the listeners to the queue and process the events asynchronously. This way the failed listener (job) will not
  block the
  processing of the other listeners.

## Processing events asynchronously

The events are processed synchronously by default, but you can process them asynchronously by implementing the
`ShouldQueue` interface.

```php
# app/Listeners/NotifyUser.php

namespace App\Listeners;

use Djereg\Laravel\RabbitMQ\Listeners\MessageEventListener;

class NotifyUser extends MessageEventListener implements ShouldQueue
{
    // Listener content
}
```

## Subscribing to events

When starting the consumer, it automatically creates the exchange and the queue if they do not exist,
but to register the events listening to, you have to modify the `EventServiceProvider` to extend the
`EventServiceProvider` provided by this package.
In Laravel 11 the `EventServiceProvider` does not exist by default, so you have to create and register it manually.
See the example below.

```php
# app/Providers/EventServiceProvider.php

namespace App\Providers;

use Djereg\Laravel\RabbitMQ\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    // Provider content
}
```

The service provider discovers all event listeners from the `app/Listeners` directory and the routing keys will be bound
automatically when the consumer started.

# RPC

A synchronous-like communication between services.

Uses the [JSON-RPC 2.0](https://www.jsonrpc.org/specification) protocol for communication.

## Registering clients

To call remote procedures, you have to create an instance of the `Client` class and inject it into the service where you
want to use it.
The best way is to do it in a service provider.

```php
# app/Providers/AppServiceProvider.php

namespace App\Providers;

use Djereg\Laravel\RabbitMQ\Services\Client;

class AppServiceProvider extends ServiceProvider {

    public function register(): void
    {
        $this->app->singleton(UserService::class, function($app) {

            // Instantiate the client with the remote service name and the queue connection
            $client = new Client('users', $app['queue.connection'])

            // Inject the client into the service
            return new UserService($client);
        });
    }
}
```

Anyway, you can create the client instance wherever you want, but remember to pass the queue connection as the second
argument.

```php
$client = new Client('users', app('queue.connection'));
```

## Calling remote procedures

In the service where you injected the client, you can call the remote procedures.

```php
# app/Services/UserService.php

namespace App\Services;

use Djereg\Laravel\RabbitMQ\Services\Client;

class UserService {

    public function __construct(private Client $users) {}

    public function getUser(int $id): mixed
    {
        // Call the remote procedure
        $user = $this->users->call('get', ['id' => $id]);

        // Process the response and return it
    }
}
```

## Registering remote procedures

Register the `ProcedureServiceProvider` provided by this package.
The service provider will automatically discover all procedures in the `app/Procedures` directory by default.
The automatic discovery runs only when the application is started in console mode.

If you want to customize the service provider, you can create your own that extends the `ProcedureServiceProvider` class
provided by this package and register it.

```php
# app/Providers/ProcedureServiceProvider.php

namespace App\Providers;

use Djereg\Laravel\RabbitMQ\Providers\ProcedureServiceProvider as ServiceProvider;

class ProcedureServiceProvider extends ServiceProvider
{
    //
}
```

## Handling procedure calls

When the service provider is registered, you can create the procedures in the `app/Procedures` directory.
Create a class that extends the `Procedure` class and define the `method` property with the name of the procedure.

```php
# app/Procedures/GetUser.php

namespace App\Procedures;

use Djereg\Laravel\RabbitMQ\Procedures\Procedure;

class GetUser extends Procedure {

    // Set the procedure name that will be called by the client
    public static string $name = 'get';

    public function __invoke(int $id): mixed
    {
        // Get the user from the database and return it
    }

    // OR

    public function handle(int $id): mixed
    {
        // Get the user from the database and return it
    }
}
```

You can define the `__invoke()` or `handle()` method to process the procedure call.
If both methods are defined, an exception will be thrown as multiple handlers are not allowed for one procedure.
The same applies to multiple procedure classes with the same name.

## How the procedure calls are processed?

When a procedure call message is received, the request body is passed
to [datto/php-json-rpc](https://github.com/datto/php-json-rpc) server component, which processes the request and calls
the matching procedure, and finally returns the response object, which is sent back to the requester.

# Laravel Queue

The [Laravel Queue](https://laravel.com/docs/11.x/queues) is also supported by this package. You can send jobs to the
queue and the consumer will process them
as the original Laravel queue worker.

# Lifecycle Events

The package emits events during the message processing.

## MessagePublishing

Dispatched before the message is being published.

```php
use Djereg\Laravel\RabbitMQ\Events\MessagePublishing;
```

## MessagePublished

Dispatched after the message is published.

```php
use Djereg\Laravel\RabbitMQ\Events\MessagePublished;
```

## MessageReceived

Dispatched when the message is received.

```php
use Djereg\Laravel\RabbitMQ\Events\MessageReceived;
```

## MessageProcessing

Dispatched before the message is being processed.

```php
use Djereg\Laravel\RabbitMQ\Events\MessageReceived;
```

## MessageProcessed

Dispatched after the message is processed.

```php
use Djereg\Laravel\RabbitMQ\Events\MessageProcessed;
```

# Known Issues

- NO TESTS! I know, I know. I will write them soon.

# License

[MIT licensed](LICENSE)

<?php

return [

    'driver'     => 'rabbitmq',
    'connection' => 'default',

    'queue' => env('RABBITMQ_QUEUE', 'default'),

    'hosts' => [
        [
            'host'     => env('RABBITMQ_HOST', '127.0.0.1'),
            'port'     => env('RABBITMQ_PORT', 5672),
            'user'     => env('RABBITMQ_USER', 'guest'),
            'password' => env('RABBITMQ_PASSWORD', 'guest'),
            'vhost'    => env('RABBITMQ_VHOST', '/'),
        ],
    ],

    'options' => [

        'queue' => [
            'exchange'      => env('RABBITMQ_EXCHANGE_NAME') ?? env('RABBITMQ_EXCHANGE', ''),
            'exchange_type' => env('RABBITMQ_EXCHANGE_TYPE', 'direct'),

            'job' => \Djereg\Laravel\RabbitMQ\Jobs\RabbitMQJob::class,
        ],
    ],

    /*
     * Set to "horizon" if you wish to use Laravel Horizon.
     */
    'worker'  => \Djereg\Laravel\RabbitMQ\Queues\RabbitMQQueue::class,

];

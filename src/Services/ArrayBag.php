<?php

namespace Djereg\Laravel\RabbitMQ\Services;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;

readonly class ArrayBag implements Arrayable
{
    public function __construct(
        private array $array,
    ) {
        //
    }

    public function toArray(): array
    {
        return $this->array;
    }

    public function all(): array
    {
        return $this->array;
    }

    public function has($key): bool
    {
        return Arr::has($this->array, $key);
    }

    /**
     * Get an item from payload using "dot" notation.
     *
     * @param string $key
     * @param mixed|null $default
     *
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->array, $key, $default);
    }

    /**
     * Get the first item from payload using "dot" notation.
     *
     * @param string[] $keys
     * @param mixed|null $default
     *
     * @return mixed
     */
    public function first(array $keys, mixed $default = null): mixed
    {
        foreach ($keys as $key) {
            if (Arr::has($this->array, $key)) {
                return Arr::get($this->array, $key, $default);
            }
        }
        return value($default);
    }
}

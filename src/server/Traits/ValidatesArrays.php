<?php

namespace Djereg\Laravel\RabbitMQ\RPC\Server\Traits;

use Illuminate\Contracts\Validation\Factory;
use Illuminate\Validation\ValidationException;

trait ValidatesArrays
{
    /**
     * @param $validator
     * @param array $dto
     *
     * @return array
     * @throws ValidationException
     */
    public function validateWith($validator, array $dto): array
    {
        if (is_array($validator)) {
            $validator = $this->getValidationFactory()->make($dto, $validator);
        }

        return $validator->validate();
    }

    /**
     * Validate the given request with the given rules.
     *
     * @param array $dto
     * @param array $rules
     * @param array $messages
     * @param array $attributes
     *
     * @return array
     *
     * @throws ValidationException
     */
    public function validate(array $dto, array $rules, array $messages = [], array $attributes = []): array
    {
        $validator = $this->getValidationFactory()->make($dto, $rules, $messages, $attributes);

        return $validator->validate();
    }

    /**
     * Get a validation factory instance.
     *
     * @return Factory
     */
    protected function getValidationFactory(): Factory
    {
        return app(Factory::class);
    }
}

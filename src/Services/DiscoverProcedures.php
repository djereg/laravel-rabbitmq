<?php

namespace Djereg\Laravel\RabbitMQ\Services;

use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use SplFileInfo;
use Symfony\Component\Finder\Finder;

class DiscoverProcedures
{
    /**
     * The callback to be used to guess class names.
     *
     * @var callable(SplFileInfo, string): string|null
     */
    public static $guessClassNamesUsingCallback;

    /**
     * Get all the events and listeners by searching the given listener directory.
     *
     * @param string $procedurePath
     * @param string $basePath
     *
     * @return array
     */
    public static function within(string $procedurePath, string $basePath): array
    {
        $procedures = collect(static::getProcedureNames(
            Finder::create()->files()->in($procedurePath), $basePath
        ));

        $discovered = [];

        foreach ($procedures as $handler => $name) {
            $discovered[$name] = $handler;
        }

        return $discovered;
    }

    /**
     * Get all the listeners and their corresponding events.
     *
     * @param iterable $procedures
     * @param string $basePath
     *
     * @return array
     */
    protected static function getProcedureNames(iterable $procedures, string $basePath): array
    {
        $procedureNames = [];

        foreach ($procedures as $procedure) {
            try {
                $procedure = new ReflectionClass(
                    static::classFromFile($procedure, $basePath)
                );
            } catch (ReflectionException) {
                continue;
            }

            if (!$procedure->isInstantiable()) {
                continue;
            }

            foreach ($procedure->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {

                if (!Str::is('handle', $method->name) && !Str::is('__invoke', $method->name)) {
                    continue;
                }

                $class = $procedure->name;
                $name = $class::$name ?? '';

                if (!$name) {
                    continue;
                }

                $procedureNames[$class . '@' . $method->name] = $name;
            }
        }

        return array_filter($procedureNames);
    }

    /**
     * Extract the class name from the given file path.
     *
     * @param \SplFileInfo $file
     * @param string $basePath
     *
     * @return string
     */
    protected static function classFromFile(SplFileInfo $file, string $basePath): string
    {
        if (static::$guessClassNamesUsingCallback) {
            return call_user_func(static::$guessClassNamesUsingCallback, $file, $basePath);
        }

        $class = trim(Str::replaceFirst($basePath, '', $file->getRealPath()), DIRECTORY_SEPARATOR);

        return str_replace(
            [DIRECTORY_SEPARATOR, ucfirst(basename(app()->path())) . '\\'],
            ['\\', app()->getNamespace()],
            ucfirst(Str::replaceLast('.php', '', $class))
        );
    }

    /**
     * Specify a callback to be used to guess class names.
     *
     * @param callable(SplFileInfo, string): string $callback
     *
     * @return void
     */
    public static function guessClassNamesUsing(callable $callback)
    {
        static::$guessClassNamesUsingCallback = $callback;
    }
}

<?php

namespace Lightning\Promise;

use Throwable;
use React\Promise\PromiseInterface;
use function React\Promise\{reject, resolve};

class Tunnel
{
    private static $storage = [];

    private function __construct()
    {
    }

    public static function get(string $key, $mixed): ?PromiseInterface
    {
        if (isset(self::$storage[$key])) {
            return self::$storage[$key];
        }

        $promise = null;
        if (is_callable($mixed)) {
            return self::get($key, call_user_func($mixed));
        } elseif ($mixed instanceof PromiseInterface) {
            $promise = $mixed;
        } elseif ($mixed instanceof Throwable) {
            $promise = reject($mixed);
        } else {
            $promise = resolve($mixed);
        }
        self::$storage[$key] = $promise;

        $callback = function ($value) use ($key) {
            unset(self::$storage[$key]);
            if ($value instanceof Throwable) {
                throw $value;
            } else {
                return $value;
            }
        };
        return $promise->then($callback, $callback);
    }

    public static function uniqueId(): string
    {
        return md5(json_encode(func_get_args()));
    }
}

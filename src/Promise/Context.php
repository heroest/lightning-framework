<?php
namespace Lightning\Promise;

use React\Promise\PromiseInterface;

class Context
{
    private static $storage = [];

    private function __construct() {}

    public static function get(string $key): ?PromiseInterface
    {
        if (isset(self::$storage[$key])) {
            return self::$storage[$key];
        } else {
            return null;
        }
    }

    public static function set($key, PromiseInterface $promise)
    {
        self::$storage[$key] = $promise;
        $closure = function($val) use ($key) {
            unset(self::$storage[$key]);
            return $val;
        };
        $promise->then($closure, $closure);
    }

    public static function cacheKey(): string
    {
        return md5(json_encode(func_get_args()));
    }
}
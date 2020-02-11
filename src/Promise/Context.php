<?php
namespace Lightning\Promise;

use React\Promise\PromiseInterface;
use function React\Promise\resolve;

class Context
{
    private static $storage = [];

    private function __construct() {}

    public static function get(string $key, $mixed): ?PromiseInterface
    {
        if (isset(self::$storage[$key])) {
            return self::$storage[$key];
        }
        
        if (is_object($mixed) and is_callable($mixed)) {
            return self::get($key, call_user_func($mixed));
        } 

        $promise = $mixed instanceof PromiseInterface ? $mixed : resolve($mixed);
        self::$storage[$key] = $promise;
        $closure = function($val) use ($key) {
            unset(self::$storage[$key]);
            return $val;
        };
        $promise->then($closure, $closure);
        return $promise;
    }

    public static function cacheKey(): string
    {
        return md5(json_encode(func_get_args()));
    }
}
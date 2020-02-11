<?php

namespace Lightning;

/**
 * Block-wait for promise to resolve or reject
 *
 * @param PromiseInterface $promise
 * @param StreamSelectLoop|null $loop
 * @return mixed
 */
function await(\React\Promise\PromiseInterface $promise, ?\Lightning\Base\AwaitableLoopInterface $loop = null)
{
    if ($loop === null) {
        $loop = \lightning\loop();
    }

    $nested = clone $loop;
    $resolved = false;
    $result = null;
    $callback = function ($value) use (&$nested, &$result, &$resolved) {
        $resolved = true;
        $result = $value;
        $nested->stop();
        unset($nested);
        return $value;
    };
    $promise->then($callback, $callback);

    if (!$resolved) {
        $nested->run();
    }
    return $result;
}

/**
 * Check if input array is a associateive array
 *
 * @param array $arr
 * @return boolean
 */
function isAssoc(array $arr)
{
    return range(0, count($arr) - 1) !== array_keys($arr);
}

/**
 * merge two array recursively
 *
 * @param array $base
 * @param array $other
 * @return array
 */
function arrayMergeRecursive(array $base, array $other): array
{
    foreach ($other as $key => $val) {
        if (!isset($base[$key])) {
            $base[$key] = $val;
        } elseif (is_array($val) and is_array($base[$key])) {
            if (!isAssoc($val) and !isAssoc($base[$key])) {
                $base[$key] = $val;
            } else {
                $base[$key] = arrayMergeRecursive($base[$key], $val);
            }
        } else {
            $base[$key] = $val;
        }
    }
    return $base;
}

function arrayCount($mixed): int
{
    if (!is_array($mixed)) {
        return 1;
    } elseif (isAssoc($mixed)) {
        $count = 0;
        foreach ($mixed as $nested) {
            $count += arrayCount($nested);
        }
        return $count;
    } else {
        return count($mixed);
    }
}

function msDate($format = 'Y-m-d H:i:s.u'): string
{
    list($sec, $usec) = explode('.', microtime(true));
    $usec = (strlen($usec) < 3) ? str_pad($usec, 3, '0', STR_PAD_RIGHT) : substr($usec, 0, 3);
    $format = str_replace('u', $usec, $format);
    return date($format, $sec);
}

/**
 * get object id
 *
 * @param object $object
 * @return string
 */
function getObjectId($object)
{
    return \md5(\spl_object_hash($object));
}

/**
 * get container
 *
 * @return \Lightning\System\Container
 */
function container(): \Lightning\System\Container
{
    return \Lightning\System\Container::getInstance();
}

/**
 * container: get loop
 *
 * @return \Lightning\Base\AwaitableLoopInterface
 */
function loop(): \Lightning\Base\AwaitableLoopInterface
{
    return \Lightning\container()->get('loop');
}

/**
 * container: get config
 *
 * @return \Lightning\System\Config
 */
function config(): \Lightning\System\Config
{
    return \Lightning\container()->get('config');
}

/**
 * turn backslash(windows) into slash(linux)
 *
 * @param string $path
 * @return string
 */
function uxPath(string $path): string
{
    return strtr($path, '\\', '/');
}

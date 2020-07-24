<?php

namespace Lightning;

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
    $usec = (strlen($usec) < 4) ? str_pad($usec, 4, '0', STR_PAD_RIGHT) : substr($usec, 0, 4);
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
 * get eventloop instance
 *
 * @return \Lightning\EventLoop\ExtendEventLoopInterface
 */
function loop(): \Lightning\EventLoop\ExtendEventLoopInterface
{
    static $loop = null;
    if (null === $loop) {
        $loop = \Lightning\EventLoop\LoopFactory::buildLoop();
    }
    return $loop;
}

function nextTick(callable $callback): void
{
    \Lightning\loop()->futureTick($callback);
}

/**
 * JS-Style setTimeout
 *
 * @param callable $callback
 * @param float $timeout
 * @return \React\EventLoop\TimerInterface
 */
function setTimeout(callable $callback, float $timeout): \React\EventLoop\TimerInterface
{
    return \Lightning\loop()->addTimer($timeout, $callback);
}

/**
 * JS-Style setInterval
 *
 * @param callable $callback
 * @param float $interval
 * @return \React\EventLoop\TimerInterface
 */
function setInterval(callable $callback, float $interval): \React\EventLoop\TimerInterface
{
    return \Lightning\loop()->addPeriodicTimer($interval, $callback);
}

/**
 * Cancel Timer genearted by setInterval() or setTimeout()
 *
 * @param \React\EventLoop\TimerInterface $timer
 * @return void
 */
function clearTimer(\React\EventLoop\TimerInterface $timer): void
{
    \Lightning\loop()->cancelTimer($timer);
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

/**
 * try to create a coroutine to run
 *
 * @param callable $callable
 * @param array $params
 * @return \Lightning\Coroutine\Coroutine|null
 */
function co(callable $callable, $params = []): ?\Lightning\Coroutine\Coroutine
{
    /** @var \Lightning\Coroutine\CoroutineSchdule $scheduler */
    $scheduler = \Lightning\Coroutine\CoroutineScheduler::getInstance();
    return $scheduler->execute($callable, $params);
}

/**
 * Undocumented function
 *
 * @param \Lightning\Coroutine\Coroutine $coroutine
 * @return void
 */
function stopCo(\Lightning\Coroutine\Coroutine $coroutine)
{
    /** @var \Lightning\Coroutine\CoroutineSchdule $scheduler */
    $scheduler = \Lightning\Coroutine\CoroutineScheduler::getInstance();
    $scheduler->cancelCoroutine($coroutine);
}
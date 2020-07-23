<?php

namespace Lightning\EventLoop\Tick;

use SplQueue;
use React\Promise\{PromiseInterface, Deferred};
use function Lightning\co;

final class DeferTickQueue
{
    private $queue;

    public function __construct()
    {
        $this->queue = new SplQueue();
    }

    public function add(callable $callable): void
    {
        $this->queue->enqueue($callable);
    }

    public function tick(): void
    {
        $count = $this->queue->count();
        while ($count--) {
            co($this->queue->dequeue());
        }
    }

    public function isEmpty()
    {
        return 0 === $this->queue->count();
    }
}
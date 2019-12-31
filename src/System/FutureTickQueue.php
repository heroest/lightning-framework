<?php

namespace Lightning\System;

use SplQueue;

/**
 * A tick queue implementation that can hold multiple callback functions
 *
 * This class should only be used internally, see LoopInterface instead.
 *
 * @see LoopInterface
 * @internal
 */
final class FutureTickQueue
{
    private $queue;

    public function __construct()
    {
        $this->queue = new SplQueue();
    }

    /**
     * Add a callback to be invoked on a future tick of the event loop.
     *
     * Callbacks are guaranteed to be executed in the order they are enqueued.
     *
     * @param callable $listener The callback to invoke.
     */
    public function add($listener)
    {
        $this->queue->enqueue($listener);
    }

    /**
     * Flush the callback queue.
     */
    public function tick()
    {
        while (!$this->queue->isEmpty()) {
            \call_user_func(
                $this->queue->dequeue()
            );
        }
    }

    /**
     * Check if the next tick queue is empty.
     *
     * @return boolean
     */
    public function isEmpty()
    {
        return $this->queue->isEmpty();
    }
}

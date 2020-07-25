<?php

namespace Lightning\Coroutine;

use Lightning\Coroutine\CoroutineException;
use Generator;
use Throwable;
use React\Promise\{Deferred, PromiseInterface};

class Coroutine
{
    const STATE_IDLE = 'idle';
    const STATE_WORKING = 'working';
    const STATE_PROGRESS = 'progress';

    /** @var int $count */
    private static $count = 1;  
    /** @var Generator $coroutine */
    private $coroutine;
    /** @var string $coroutineId */
    private $coroutineId;
    /** @var \Lightning\Coroutine\Coroutine $parent */
    private $parent;
    /** @var \Lightning\Coroutine\Coroutine $child */
    private $child;
    /** @var \React\Promise\CancellablePromiseInterface $progress */
    private $progress;
    /** @var string $state */
    private $state = '';
    /** @var float $timeStartWorking */
    private $timeStartWorking = 0;
    /** @var float $timeDurationStart */
    private $timeDurationStart = 0;
    /** @var float $timeout */
    private $timeout = 0;
    /** @var \React\Promise\Deferred $deferred */
    private $deferred;


    public function __construct()
    {
        $this->coroutineId = self::$count++;
        $this->progress = null;
        $this->deferred = null;
        $this->changeState(self::STATE_IDLE);
    }

    public function initialize(Generator $coroutine, float $timeout = 30): self
    {
        $this->deferred = new Deferred(function () {
            $this->cancel('promise-cancelling');
            $this->reset();
        });
        $this->changeState(self::STATE_WORKING);
        $this->coroutine = $coroutine;
        $this->timeStartWorking = microtime(true);
        $this->setCoroutineTimeout($timeout);
        return $this;
    }

    public function promise(): ?PromiseInterface
    {
        return $this->deferred ? $this->deferred->promise() : null;
    }

    public function inState($state)
    {
        return $this->state === $state;
    }

    public function getState()
    {
        return $this->state;
    }

    public function getCoroutineId(): int
    {
        return $this->coroutineId;
    }

    public function set($mixed)
    {
        if (!$this->valid()) {
            return;
        }

        try {
            ($mixed instanceof Throwable) 
                ? $this->coroutine->throw($mixed) 
                : $this->coroutine->send($mixed);
        } catch (Throwable $e) {
            $this->settle($e);
            throw $e;
        }
    }

    public function get()
    {
        if ($this->coroutine->valid()) {
            return $this->coroutine->current();
        } else {
            $this->coroutine->next();
            $return = $this->coroutine->getReturn();
            $this->settle($return);
            return $return;
        }
    }

    public function valid()
    {
        return (null !== $this->coroutine) and $this->coroutine->valid();
    }

    public function parent(): ?Coroutine
    {
        return $this->parent;
    }

    public function setParent(Coroutine $parent): self
    {
        $this->parent = $parent;
        return $this;
    }

    public function child(): ?Coroutine
    {
        return $this->child;
    }

    public function setChild(Coroutine $child): self
    {
        $this->child = $child;
        return $this;
    }

    public function appendProgress(PromiseInterface $promise): self
    {
        if ($this->progress !== $promise) {
            $callable = function () {
                $this->progress = null;
                $this->changeState(self::STATE_WORKING);
            };
            $this->progress = $promise;
            $this->changeState(self::STATE_PROGRESS);
            $promise->then($callable, $callable);
        }
        return $this;
    }

    public function getStateDuration(): float
    {
        return floatval(bcsub(microtime(true), $this->timeDurationStart, 4));
    }

    public function setCoroutineTimeout(float $timeout): self
    {
        $this->timeout = $timeout;
        return $this;
    }

    public function isOverTime(): bool
    {
        return empty($this->timeStartWorking) 
                ? false 
                : floatval(bcsub(microtime(true), $this->timeStartWorking, 4)) > $this->timeout;
    }

    public function cancel(string $reason = ''): self
    {   
        if (null !== $this->progress) {
            $this->progress->cancel();
            $this->progress = null;
        }

        $exception_msg = "Coroutine has been cancelled";
        $exception_msg .= $reason ? " due to {$reason}" : '';
        $this->settle(new CoroutineException($exception_msg));
        $this->coroutine = null;
        return $this;
    }

    public function reset()
    {
        $this->changeState(self::STATE_IDLE);
        $this->coroutine = null;
        $this->deferred = null;
        $this->parent = null;
        $this->child = null;
        $this->timeStartWorking = 0;
    }

    private function changeState($state)
    {
        $this->state = $state;
        $this->timeDurationStart = microtime(true);
    }

    private function settle($value)
    {
        if ($value instanceof Throwable) {
            $this->deferred->reject($value);
        } else {
            $this->deferred->resolve($value);
        }
    }
}
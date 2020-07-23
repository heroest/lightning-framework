<?php

namespace Lightning\Coroutine\SystemCall;

use Lightning\Coroutine\Coroutine;

class SetPromiseTimeout extends AbstractSystemCall
{
    private $timeout = 0;

    public function __construct(float $timeout)
    {
        $this->timeout = $timeout;
    }

    public function execute(Coroutine $coroutine)
    {
        $coroutine->setPromiseTimeout($this->timeout);
    }
}
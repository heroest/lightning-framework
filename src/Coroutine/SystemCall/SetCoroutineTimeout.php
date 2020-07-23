<?php

namespace Lightning\Coroutine\SystemCall;

use Lightning\Coroutine\SystemCall\AbstractSystemCall;
use Lightning\Coroutine\Coroutine;

class SetCoroutineTimeout extends AbstractSystemCall
{
    private $timeout;

    public function __construct(float $timeout)
    {
        $this->timeout = $timeout;
    }

    public function execute(Coroutine $coroutine)
    {
        $coroutine->setCoroutineTimeout($this->timeout);
    }
}
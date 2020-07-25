<?php

namespace Lightning\Coroutine\SystemCall;

use Lightning\Coroutine\SystemCall\InterfaceSystemCall;
use Lightning\Coroutine\Coroutine;

class SetCoroutineTimeout implements InterfaceSystemCall
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
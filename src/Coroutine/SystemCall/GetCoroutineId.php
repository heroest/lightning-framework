<?php

namespace Lightning\Coroutine\SystemCall;

use Lightning\Coroutine\SystemCall\InterfaceSystemCall;
use Lightning\Coroutine\Coroutine;

class GetCoroutineId implements InterfaceSystemCall
{
    public function execute(Coroutine $coroutine)
    {
        return $coroutine->getCoroutineId();
    }
}
<?php

namespace Lightning\Coroutine\SystemCall;

use Lightning\Coroutine\SystemCall\AbstractSystemCall;
use Lightning\Coroutine\Coroutine;

class GetCoroutineId extends AbstractSystemCall
{
    public function execute(Coroutine $coroutine)
    {
        return $coroutine->getCoroutineId();
    }
}
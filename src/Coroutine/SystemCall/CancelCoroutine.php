<?php

namespace Lightning\Coroutine\SystemCall;

use Lightning\Coroutine\SystemCall\InterfaceSystemCall;
use Lightning\Coroutine\{Coroutine, CoroutineScheduler};

class CancelCoroutine implements InterfaceSystemCall
{
    public function execute(Coroutine $coroutine)
    {
        /** @var CoroutineScheduler $scheduler */
        $scheduler = CoroutineScheduler::getInstance();
        return $scheduler->cancelCoroutine($coroutine);
    }
}

<?php

namespace Lightning\Coroutine\SystemCall;

use Lightning\Coroutine\SystemCall\AbstractSystemCall;
use Lightning\Coroutine\{Coroutine, CoroutineScheduler};

class CancelCoroutine extends AbstractSystemCall
{
    public function execute(Coroutine $coroutine)
    {
        /** @var CoroutineScheduler $scheduler */
        $scheduler = CoroutineScheduler::getInstance();
        return $scheduler->cancelCoroutine($coroutine);
    }
}

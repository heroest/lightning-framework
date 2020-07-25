<?php

namespace Lightning\Coroutine\SystemCall;

use Lightning\Coroutine\Coroutine;

interface InterfaceSystemCall
{
    public function execute(Coroutine $coroutine);
}
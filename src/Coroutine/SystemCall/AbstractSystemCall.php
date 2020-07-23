<?php

namespace Lightning\Coroutine\SystemCall;

use Lightning\Coroutine\Coroutine;

abstract class AbstractSystemCall
{
    public abstract function execute(Coroutine $coroutine);
}
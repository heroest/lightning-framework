<?php

namespace Lightning\EventLoop;

use React\EventLoop\LoopInterface;

interface ExtendEventLoopInterface extends LoopInterface
{
    /**
     * 添加下一次事件循环的调用
     *
     * @param callable $callable
     * @param array $params
     * @return void
     */
    public function defer(callable $callable): void;
}
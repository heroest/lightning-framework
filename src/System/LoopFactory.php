<?php 
namespace Lightning\System;

class LoopFactory
{
    public static function buildLoop(): \Lightning\Base\AwaitableLoopInterface
    {
        if (class_exists('EventBase')) {
            return new \Lightning\System\ExtEventLoop();
        } elseif (function_exists('uv_loop_new') and false) {
            return new \Lightning\System\ExtUvLoop();
        } else {
            return new \Lightning\System\StreamSelectLoop();
        }
    }
}
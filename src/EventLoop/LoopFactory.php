<?php 
namespace Lightning\EventLoop;

class LoopFactory
{
    public static function buildLoop(): \Lightning\Base\AwaitableLoopInterface
    {
        if (class_exists('EventBase')) {
            return new \Lightning\EventLoop\ExtEventLoop();
        } elseif (function_exists('uv_loop_new') and false) {
            return new \Lightning\EventLoop\ExtUvLoop();
        } else {
            return new \Lightning\EventLoop\StreamSelectLoop();
        }
    }
}
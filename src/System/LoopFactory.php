<?php 
namespace Lightning\System;

class LoopFactory
{
    public static function buildLoop(): \Lightning\Base\AwaitableLoopInterface
    {
        return new \Lightning\System\StreamSelectLoop();
        if (function_exists('uv_loop_new')) {
            return new \Lightning\System\ExtUvLoop();
        } else {
            return new \Lightning\System\StreamSelectLoop();
        }
    }
}
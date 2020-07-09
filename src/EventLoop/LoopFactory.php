<?php 
namespace Lightning\EventLoop;

class LoopFactory
{
    public static function buildLoop(): \Lightning\Base\ExtendedEventLoopInterface
    {
        return new \Lightning\EventLoop\StreamSelectLoop();
    }
}
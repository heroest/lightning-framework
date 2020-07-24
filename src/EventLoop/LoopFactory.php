<?php 
namespace Lightning\EventLoop;

class LoopFactory
{
    public static function buildLoop(): \Lightning\EventLoop\ExtendEventLoopInterface
    {
        return new \Lightning\EventLoop\StreamSelectLoop();
    }
}
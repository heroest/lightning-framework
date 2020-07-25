<?php

namespace Lightning\Coroutine\SystemCall;

use Lightning\Coroutine\Coroutine;
use Lightning\Database\ConnectionPool;
use Lightning\Coroutine\SystemCall\InterfaceSystemCall;

/**
 * 数据库连接流量阀
 */
class DatabaseQueryThreshold implements InterfaceSystemCall
{
    public function execute(Coroutine $coroutine)
    {
        /** @var ConnectionPool $pool */
        $pool = ConnectionPool::getInstance();
        return $pool->getPendingConnectionThresholdLock();
    }
}
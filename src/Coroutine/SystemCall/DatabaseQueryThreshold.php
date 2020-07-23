<?php

namespace Lightning\Coroutine\SystemCall;

use Lightning\Coroutine\Coroutine;
use Lightning\Database\ConnectionPool;

/**
 * 数据库连接流量阀
 */
class DatabaseQueryThreshold extends AbstractSystemCall
{
    public function execute(Coroutine $coroutine)
    {
        /** @var ConnectionPool $pool */
        $pool = ConnectionPool::getInstance();
        return $pool->getPendingConnectionThresholdLock();
    }
}
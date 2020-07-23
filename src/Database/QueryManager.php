<?php

namespace Lightning\Database;

use Throwable;
use SplObjectStorage;
use Lightning\Database\{DatabaseException, ConnectionPool, Connection};
use Lightning\Base\AbstractSingleton;
use React\EventLoop\TimerInterface;
use React\Promise\{Deferred, PromiseInterface};
use function React\Promise\Timer\timeout;
use function React\Promise\resolve;
use function Lightning\{loop, setInterval, clearTimer};

class QueryManager extends AbstractSingleton
{
    
    /**
     * 数据库连接池
     * @var ConnectionPool $pool
     */
    private $pool;

    /** 
     * 工作中的mysqli实例
     * @var SplObjectStorage $working 
     */
    private $working;

    /**
     * 数据库请求异步轮询结果
     * @var TimerInterface $polling
     */
    private $polling;

    protected function __construct()
    {
        $this->working = new SplObjectStorage();
    }

    public function setConnectionPool(ConnectionPool $pool)
    {
        $this->pool = $pool;
    }

    /**
     * 中止一个事务进程并关闭连接
     *
     * @param Transaction $transaction
     * @param float $timeout
     * @return PromiseInterface
     */
    public function terminateTransactionConnection(Transaction $transaction): PromiseInterface
    {
        if ($connection = $transaction->getConnection()) {
            $connection->close();
            foreach ($this->working as $link) {
                if ($this->working[$link] === $connection) {
                    $this->working->detach($link);
                }
            }
        }
        return resolve(true);
    }

    public function startTransaction(Transaction $transaction, float $timeout): PromiseInterface
    {
        $connected = $this->pool
            ->getConnection($transaction->getConnectionName(), 'master')
            ->then(function (Connection $connection) use ($transaction) {
                $connection->beginTransaction($transaction);
                $transaction->bindConnection($connection);
            });
        return timeout($connected, $timeout, loop());
    }

    public function closeTransanction(Transaction $transaction, float $timeout)
    {
        $connected = $this->pool->getConnection(
                        $transaction->getConnectionName(),
                        'master',
                        $transaction
                    );
        $connected->then(function (Connection $connection) {
            $connection->closeTransanction();
        });
        return timeout($connected, $timeout, loop());
    }

    public function execute(Query $query): PromiseInterface
    {
        /** @var \React\Promise\CancellablePromiseInterface $connected*/
        $connected = null;
        $link = null;
        $deferred = new Deferred(function () use (&$link, &$connected) {
            if (null !== $connected) {
                $connected->cancel();
            }
            
            if ((null !== $link) and $this->working->contains($link)) {
                $connection = $this->working[$link];
                $this->working->detach($link);
                $connection->close();
            }
            throw new DatabaseException("Query execution is canncelled due to promise-cancelling");
        });

        $connected = $this->pool->getConnection(
                        $query->getConnectionName(),
                        $query->getConnectionRole(),
                        $query->getTransaction()
                    );
        $connected->then(function (Connection $connection) use ($query, $deferred, &$link) {
                $link = $connection->getLink();
                $this->working->attach($link, $connection);

                $promise = $connection->query(
                                $query->getSql(), 
                                $query->getParams()
                            );
                $deferred->resolve($promise);
                $this->startPolling();
            }, function ($error)  use ($deferred) {
                $deferred->reject($error);
            });
        return timeout($deferred->promise(), $query->getMaxExecutionTime(), loop());
    }

    private function startPolling()
    {
        //轮询已启动
        if (null !== $this->polling) {
            return;
        }

        $this->polling = setInterval(function () {
            $read = $error = $reject = [];
            foreach ($this->working as $link) {
                $read[] = $error[] = $reject[] = $link;
            }

            if (!mysqli_poll($read, $error, $reject, 0)) {
                return;
            }

            foreach ($read as $link) {
                /** @var \mysqli $link */
                $connection = $this->working[$link];
                $this->working->detach($link);
                if ($result = $link->reap_async_query()) {
                    $connection->resolve($result);
                } else {
                    $connection->reject(new DatabaseException($link->error, $link->errno));
                }
            }

            foreach ($error as $link) {
                /** @var \mysqli $link */
                $connection = $this->working[$link];
                $this->working->detach($link);
                $connection->reject(new DatabaseException($link->error, $link->errno));
            }

            //3. stop if no job left
            if (0 === $this->working->count()) {
                $this->stopPolling();
            }
        }, 0);
    }

    private function stopPolling()
    {
        if (null !== $this->polling) {
            clearTimer($this->polling);
            $this->polling = null;
        }
    }
}

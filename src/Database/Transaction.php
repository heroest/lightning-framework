<?php

namespace Lightning\Database;

use SplObjectStorage;
use React\Promise\{Deferred, PromiseInterface};
use Lightning\Database\{Query, DatabaseException, Connection};
class Transaction
{
    private $connectionName = '';
    private $closed = false;
    private $settled = false;
    private $timeout = 0;
    private $connection;
    private $pendingConnected = null;
    const STATEMENT_START = 'START TRANSACTION;';
    const STATEMENT_ROLLBACK = 'ROLLBACK;';
    const STATEMENT_COMMIT = 'COMMIT;';

    private function __construct(string $connection_name, float $timeout)
    {
        $this->connectionName = $connection_name;
        $this->timeout = $timeout;
        $this->pendingConnected = new SplObjectStorage();
    }

    public function getConnectionName()
    {
        return $this->connectionName;
    }

    public function bindConnection(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function getConnection(): ?Connection
    {
        return $this->connection;
    }

    public function attachConnected(PromiseInterface $connected)
    {
        $this->pendingConnected->attach($connected);
    }

    public function detachConnected(PromiseInterface $connected)
    {
        $this->pendingConnected->detach($connected);
    }

    /**
     * 关闭数据库事务
     *
     * @param float $timeout
     * @return void
     */
    public function close(float $timeout = 30)
    {
        $this->closed = true;
        foreach ($this->pendingConnected as $connected) {
            $connected->cancel();
        }
        /** @var QueryManager $manager */
        $manager = QueryManager::getInstance();
        if ($this->settled) {
            $manager->closeTransanction($this, $timeout)
                ->otherwise(function () use ($manager) {
                    $manager->terminateTransactionConnection($this);
                });
        } else {
            $manager->terminateTransactionConnection($this);
        }
    }

    /**
     * 查询事务是否已关闭
     *
     * @return boolean
     */
    public function isClosed(): bool
    {
        return $this->closed;
    }

    /**
     * 获取数据库事务实例
     *
     * @param string $connection_name
     * @param float $timeout
     * @return self
     */
    public static function connection(string $connection_name, float $timeout = 30): self
    {
        return new self($connection_name, $timeout);
    }

    /**
     * 事务开始
     *
     * @param string $connection_name
     * @param float $timeout
     * @return PromiseInterface
     */
    public function start(): PromiseInterface
    {
        $deferred = new Deferred(function () {
            $this->close();
            throw new DatabaseException("transaction-is-timeout");
        });
        /** @var QueryManager $manager */
        $manager = QueryManager::getInstance();
        $connected = $manager->startTransaction($this, $this->timeout);
        $connected->then(
            function () use ($deferred) {
                $promise = Query::useTransaction($this)
                    ->setSql(self::STATEMENT_START)
                    ->execute();
                $deferred->resolve($promise);
            }, 
            function ($error) use ($deferred) {
                $deferred->reject($error);
            }
        );
        return $deferred->promise();
    }

    /**
     * 事务提交
     *
     * @return PromiseInterface
     */
    public function commit(): PromiseInterface
    {
        $deferred = new Deferred(function () {
            throw new DatabaseException("Transaction commit failed due to promise-canelling");
        });
        $queried = null;
        $queried = Query::useTransaction($this)
            ->setSql(self::STATEMENT_COMMIT)
            ->execute()
            ->then(
                function () use ($deferred) {
                    $this->settled = true;
                    $deferred->resolve($this->close());
                },
                function ($error) use ($deferred, &$queried) { //settle-failed, force closse connection
                    $queried->cancel();
                    $this->closed = true;
                    $deferred->reject($error);
                }
            );
        return $deferred->promise();
    }

    /**
     * 事务回滚
     *
     * @return PromiseInterface
     */
    public function rollback(): PromiseInterface
    {
        $deferred = new Deferred(function () {
            throw new DatabaseException("Transaction rollback failed due to promise-cancelling");
        });
        $queried = null;
        $queried = Query::useTransaction($this)
            ->setSql(self::STATEMENT_ROLLBACK)
            ->execute()
            ->then(
                function () use ($deferred) {
                    $this->settled = true;
                    $deferred->resolve($this->close());
                },
                function ($error) use ($deferred, &$queried) { //settle-failed, force closse connection
                    $queried->cancel();
                    $this->closed = true;
                    $deferred->reject($error);
                }
            );
        return $deferred->promise();
    }
}
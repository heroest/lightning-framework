<?php

namespace Lightning\Database;

use SplObjectStorage;
use Lightning\Database\{Pool, Connection, Query};
use React\Promise\{PromiseInterface, Deferred};
use function Lightning\{clearTimer, setInterval, watch};
use Lightning\Exceptions\DatabaseException;

class DBManager
{
    private $pool;
    private $polling = null;
    private $working;

    public function __construct(Pool $pool)
    {
        $this->pool = $pool;
        $this->working = new SplObjectStorage();
    }

    public function getTransactionConnection(string $connection_name, Transaction $transaction): PromiseInterface
    {
        $connected = $this->pool->getConnection($connection_name, 'master');
        return $connected->then(function (Connection $connection) use ($transaction) {
            return $connection->beginTransaction($transaction);
        }, function ($error) {
            throw $error;
        });
    }

    public function execute(Query $query): PromiseInterface
    {
        $link = null;
        $max_exection_time = $query->getMaxExecutionTime();
        $canceller = function () use (&$link) {
            if ((null !== $link) and $this->working->contains($link)) {
                $connection = $this->working[$link];
                $this->working->detach($link);
                $connection->close();
            }
            throw new DatabaseException("Query timeout.");
        };
        $deferred = new Deferred($canceller);
        $promise = $deferred->promise();
        watch($promise, $max_exection_time);

        if ($query->inTransaction()) {
            $connected = $query->getTransaction()->getConnection();
        } else {
            $connected = $this->pool->getConnection(
                $query->getConnectionName(),
                $query->getConnectionRole()
            );
        }

        $connected->then(function (Connection $connection) use ($query, $deferred, &$link) {
            $link = $connection->getLink();
            $this->working->attach($link, $connection);

            $promise = $connection->query(
                $query->getSql(),
                $query->getParams(),
                $query->getFetchMode()
            );
            $deferred->resolve($promise);
            $this->startPolling();
        }, function ($error) use ($deferred) {
            $deferred->reject($error);
        });

        return $promise;
    }

    private function startPolling()
    {
        if (null !== $this->polling) {
            return;
        }

        $callback = function () {
            $read = $error = $reject = [];
            foreach ($this->working as $link) {
                $read[] = $error[] = $reject[] = $link;
            }
            if (false === mysqli_poll($read, $error, $reject, 0)) {
                return;
            }
            foreach ($read as $link) {
                $connection = $this->working[$link];
                $this->working->detach($link);
                if ($result = $link->reap_async_query()) {
                    $connection->resolve($result);
                } else {
                    $connection->reject(new DatabaseException($link->error, $link->errno));
                }
            }

            //3. stop if no job left
            if (0 === $this->working->count()) {
                $this->stopPolling();
            }
        };
        $this->polling = setInterval($callback, 0);
    }

    private function stopPolling()
    {
        if (null !== $this->polling) {
            clearTimer($this->polling);
            $this->polling = null;
        }
    }
}

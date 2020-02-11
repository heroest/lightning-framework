<?php

namespace Lightning\Database;

use SplObjectStorage;
use Lightning\Database\{Pool, Connection, Query};
use React\Promise\{PromiseInterface, Deferred};
use function Lightning\{getObjectId, loop};
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

    public function query(string $connection_name, string $role, string $sql, ?array $params = [], string $fetch_mode = 'fetch_row')
    {
        if (!in_array($fetch_mode, Connection::FETCH_MODES)) {
            throw new DatabaseException("Unknown Fetch Modes: {$fetch_mode}");
        }

        $query = new Query($connection_name, $role);
        $query->setSql($sql);
        if (!empty($params)) {
            $query->setParams($params);
        }
        $query->setFetchMode($fetch_mode);
        $this->execute($query);
    }

    public function execute(Query $query): PromiseInterface
    {
        $deferred = new Deferred();
        $connected = $this->pool->getConnection(
            $query->getConnectionName(),
            $query->getConnectionRole()
        );
        $connected->then(function (Connection $connection) use ($query, $deferred) {
            $link = $connection->getLink();
            $this->working->attach($link, $connection);

            $promise = $connection->query(
                $query->getSql(),
                $query->getParams(),
                $query->getFetchMode()
            );
            $deferred->resolve($promise);
            $this->startPolling();
        });
        return $deferred->promise();
    }

    private function startPolling()
    {
        if (null !== $this->polling) {
            return;
        }

        $this->polling = loop()->addPeriodicTimer(0, function () {
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
                    $connection->resolve(new DatabaseException($link->error, $link->errno));
                }
            }

            //stop if no something left
            if (0 === $this->working->count()) {
                $this->stopPoling();
            }
        });
    }

    private function stopPoling()
    {
        if (null !== $this->polling) {
            loop()->cancelTimer($this->polling);
            $this->polling = null;
        }
    }
}

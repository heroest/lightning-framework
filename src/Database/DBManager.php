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
    private $polling = false;
    // private $working = [];
    private $linkConnection = [];
    private $working;

    public function __construct(Pool $pool)
    {
        $this->pool = $pool;
        $this->working = new SplObjectStorage();
    }

    // public function runQuery(Query $query): PromiseInterface
    // {
    //     return $this->query(
    //         $query->getConnectionName(),
    //         $query->getConnectionRole(),
    //         $query->getSql(),
    //         $query->getFetchMode(),
    //         $query->getParams()
    //     );
    //     $this->execute($query);
    // }

    public function query(string $connection_name, string $role, string $sql, ?array $params = [], string $fetch_mode = 'fetch_row')
    {
        if (!in_array($fetch_mode, Connection::FETCH_MODES)) {
            throw new DatabaseException("Unknown Fetch Modes: {$fetch_mode}");
        }

    
        // $connection_promise = $this->pool->getConnection($connection_name, $role);
        // $promise = $this->execute($connection_promise, $sql, $fetch_mode, $params);

        // return $promise;
        $query = new Query($connection_name, $role);
        $query->setSql($sql);
        $query->setParams($params);
        $query->setFetchMode($fetch_mode);
        $this->execute($query);
    }

    // private function doExecute(PromiseInterface $connection_promise, string $sql, $fetch_mode, array $params = []): PromiseInterface
    // {
    //     $deferred = new Deferred();
    //     $connection_promise->then(function (Connection $connection) use ($deferred, $sql, $fetch_mode, $params) {
    //         $link = $connection->getLink();
    //         $link_id = getObjectId($link);
    //         $this->working[$link_id] = $link;
    //         $this->linkConnection[$link_id] = $connection;

    //         $promise = $connection->query($sql, $fetch_mode, $params);
    //         $this->connectionPoll();
    //         $deferred->resolve($promise);
    //     });
    //     return $deferred->promise();
    // }

    public function execute(Query $query): PromiseInterface
    {
        $deferred = new Deferred();
        $connected = $this->pool->getConnection(
            $query->getConnectionName(),
            $query->getConnectionRole()
        );
        $connected->then(function (Connection $connection) use ($query, $deferred) {
            $link = $connection->getLink();
            // $link_id = getObjectId($link);
            // $this->working[$link_id] = $link;
            // $this->linkConnection[$link_id] = $connection;
            $this->working->attach($link, $connection);

            $promise = $connection->query($query->getSql(), $query->getParams(), $query->getFetchMode());
            $deferred->resolve($promise);
            $this->connectionPoll();
        });
        return $deferred->promise();
    }

    private function connectionPoll()
    {
        if ($this->polling) {
            return;
        }

        $this->polling = true;
        loop()->addTimer(0, function () {
            $this->polling = false;
            if (empty($this->working)) {
                return;
            }

            $read = $error = $reject = [];
            foreach ($this->working as $link) {
                $read[] = $error[] = $reject[] = $link;
            }
            $count = mysqli_poll($read, $error, $reject, 0);
            if ($this->working->count() !== $count) {
                $this->connectionPoll();
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
        });
    }
}

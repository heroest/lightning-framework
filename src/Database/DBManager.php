<?php
namespace Lightning\Database;

use Lightning\Promise\Context;
use Lightning\Database\{Pool, Connection, Query};
use React\Promise\{PromiseInterface, Deferred};
use function Lightning\{getObjectId, loop};
use Lightning\Exceptions\DatabaseException;
use mysqli;

class DBManager
{
    private $pool;
    private $polling = false;
    private $working = [];
    private $linkConnection = [];

    public function __construct(Pool $pool)
    {
        $this->pool = $pool;
        $this->working = [];
    }

    public function runQuery(Query $query): PromiseInterface
    {
        return $this->query(
            $query->getConnectionName(),
            $query->getConnectionRole(),
            $query->getSql(),
            $query->getFetchMode(),
            $query->getParams()
        );
    }

    public function query(string $connection_name, string $role, string $sql, string $fetch_mode = 'fetch_row', array $params = [])
    {
        if (!in_array($fetch_mode, Connection::FETCH_MODES)) {
            throw new DatabaseException("Unknown Fetch Modes: {$fetch_mode}");
        }

        if (self::isReadQuery($sql)) {
            $cache_key = Context::cacheKey($connection_name, $sql, $role, $fetch_mode);
            if ($promise = Context::get($cache_key)) {
                return $promise;
            }
        }

        $connection_promise = $this->pool->getConnection($connection_name, $role);
        $promise = $this->execute($connection_promise, $sql, $fetch_mode, $params);

        if (isset($cache_key)) {
            Context::set($cache_key, $promise);
        }
        return $promise;
    }

    private function execute(PromiseInterface $connection_promise, string $sql, $fetch_mode, array $params = []): PromiseInterface
    {
        $deferred = new Deferred();
        $connection_promise->then(function(Connection $connection) use ($deferred, $sql, $fetch_mode, $params) {
            $link = $connection->getLink();
            $link_id = getObjectId($link);
            $this->working[$link_id] = $link;
            $this->linkConnection[$link_id] = $connection;

            $promise = $connection->query($sql, $fetch_mode, $params);
            $this->connectionPoll();
            $deferred->resolve($promise);
        });
        return $deferred->promise();
    }

    private function connectionPoll()
    {
        if ($this->polling) {
            return;
        }
            
        loop()->addTimer(0, function($timer) {
            $this->polling = false;
            if (empty($this->working)) {
                return;
            }

            $read = $error = $reject = $this->working;
            $count = mysqli_poll($read, $error, $reject, 0);
            if ((count($this->working) - intval($count)) > 0) {
                $this->connectionPoll();
            }

            foreach ($read as $link) {
                $link_id = getObjectId($link);
                $connection = $this->linkConnection[$link_id];
                unset(
                    $this->working[$link_id],
                    $this->linkConnection[$link_id]
                );
                if ($result = $link->reap_async_query()) {
                    $connection->resolve($result);
                } else {
                    $connection->reject(new DatabaseException($link->error, $link->errno));
                }
            }
        });
    }

    //from Yii
    private static function isReadQuery(string $sql): bool
    {
        $pattern = '/^\s*(SELECT|SHOW|DESCRIBE)\b/i';
        return preg_match($pattern, $sql) > 0;
    }
}
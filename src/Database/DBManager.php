<?php
namespace Lightning\Database;

use Lightning\Base\ArrayObject;
use Lightning\System\PendingPromises;
use Lightning\Database\{Pool, Connection, QueryResult};
use React\Promise\{PromiseInterface, Deferred};
use function Lightning\{getObjectId, awaitForResult, container};
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

    public function query(string $connection_name, string $sql, string $role = 'master', bool $fetch_row = false)
    {
        if (self::isReadQuery($sql)) {
            $cache_key = PendingPromises::cacheKey($connection_name, $sql, $role, $fetch_row);
            if ($promise = PendingPromises::get($cache_key)) {
                return $promise;
            }
        }
        $promise = null;
        $mixed = $this->pool->getConnection($connection_name, $role);
        if ($mixed instanceof PromiseInterface) {
            $promise = $this->executeAsync($mixed, $sql, $fetch_row);
        } elseif ($mixed instanceof Connection) {
            $promise = $this->execute($mixed, $sql, $fetch_row);
        } else {
            throw new DatabaseException("Unknown return type: " . gettype($mixed));
        }

        if (isset($cache_key)) {
            PendingPromises::set($cache_key, $promise);
        }
        return $promise;
    }

    private function executeAsync(PromiseInterface $connection_promise, string $sql, bool $fetch_row): PromiseInterface
    {
        $deferred = new Deferred();
        $connection_promise->then(function($connection) use ($deferred, $sql, $fetch_row) {
            $deferred->resolve($this->execute($connection, $sql, $fetch_row));
        });
        unset($connection_promise);
        return $deferred->promise();
    }

    private function execute(Connection $connection, string $sql, bool $fetch_row): PromiseInterface
    {
        $link = $connection->getLink();
        $link_id = getObjectId($link);
        $this->working[$link_id] = $link;
        $this->linkConnection[$link_id] = $connection;

        $promise = $connection->query($sql, $fetch_row);
        $this->connectionPoll();
        return $promise;
    }

    private function connectionPoll()
    {
        if ($this->polling) {
            return;
        } else {
            container()
            ->get('loop')
            ->addTimer(0, function($timer) {
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
                        $connection->resolve(self::fetchQueryResult($result, $link));
                    } else {
                        $connection->reject(new DatabaseException($link->error, $link->errno));
                    }
                }
            });
        }
    }

    private static function fetchQueryResult($result, \mysqli $link)
    {
        if (is_object($result)) {
            $data = new QueryResult($result->fetch_all(MYSQLI_ASSOC));
            mysqli_free_result($result);
        } else {
            $data = new QueryResult(null, $link->insert_id, $link->affected_rows);
        }
        return $data;
    }

    //from Yii
    private static function isReadQuery(string $sql): bool
    {
        $pattern = '/^\s*(SELECT|SHOW|DESCRIBE)\b/i';
        return preg_match($pattern, $sql) > 0;
    }
}
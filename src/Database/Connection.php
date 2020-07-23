<?php

namespace Lightning\Database;

use mysqli;
use mysqli_result;
use Throwable;
use React\Promise\{Deferred, PromiseInterface};
use Lightning\Database\{DatabaseException, Transaction, Expression, QueryResult};
use function React\Promise\{reject, resolve};

class Connection
{
    const STATE_CLOSE = 0;
    const STATE_IDLE = 1;
    const STATE_WORKING = 2;

    /**
     * 数据库状态
     *
     * @var int
     */
    private $state;

    /**
     * 数据库状态开始时间
     *
     * @var float
     */
    private $stateTimeStart;

    /** 
     * 链接名称
     * @var string
     */
    private $connectionName;

    /** 
     * 链接身份 (master / slave)
     * @var string
     */
    private $connectionRole;

    /** 
     * 数据库链接实例
     * @var mysqli $link 
     */
    private $link;

    /**
     * 数据库配置
     *
     * @var array
     */
    private $credential = [];

    /**
     * 链接的概述
     *
     * @var array
     */
    private $profile = [];

    /**
     * 返回数据Deferred
     *
     * @var \React\Promise\Deferred $deferred
     */
    private $deferred;

    /**
     * 数据库事务
     *
     * @var null|\Lightning\Database\Transaction
     */
    private $transaction = null;

    public function __construct(string $name, string $role, array $option)
    {
        $this->connectionName = $name;
        $this->connectionRole = $role;
        $this->credential = [
            'host' => $option['host'],
            'username' => $option['username'],
            'password' => $option['password'],
            'dbname' => $option['dbname'],
            'port' => $option['port']
        ];
        $this->changeState(self::STATE_CLOSE);
        $this->updateProfile('time_created', microtime(true));
    }

    public function getName(): string
    {
        return $this->connectionName;
    }

    public function getRole(): string
    {
        return $this->connectionRole;
    }

    public function getLink(): ?mysqli
    {
        return $this->link;
    }

    public function open(): bool
    {
        if (self::STATE_IDLE === $this->state) {
            return true;
        } elseif (self::STATE_CLOSE !== $this->state) {
            return false;
        }

        $credential = $this->credential;
        try {
            $this->link = new mysqli(
                $credential['host'],
                $credential['username'],
                $credential['password'],
                $credential['dbname'],
                $credential['port']
            );
            $this->link->query('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');
            $this->changeState(self::STATE_IDLE);
            $this->updateProfile('time_opened', microtime(true));
            return true;
        } catch (Throwable $e) {
            echo $e;
            $this->updateProfile('time_opened_failed', microtime(true));
            return false;
        }
    }

    public function getConnectedDuration(): float
    {
        return isset($this->profile['time_opened'])
                ? floatval(bcsub(microtime(true), $this->profile['time_opened'], 4))
                : 0;
    }

    public function close()
    {
        if (null !== $this->link) {
            $this->link->kill($this->link->thread_id);
            $this->link->close();
            $this->link = null;
        }

        if (null !== $this->deferred) {
            $this->deferred->reject(new DatabaseException('Database Connection has been closed'));
        }
        $this->deferred = null;
        $this->transaction = null;
        $this->changeState(self::STATE_CLOSE);
    }

    public function ping(): bool
    {
        if ($this->link === null) {
            return false;
        } else {
            return $this->link->ping();
        }
    }

    public function getState(): string
    {
        return $this->state;
    }

    public function inState(int $state): bool
    {
        return $state === $this->state;
    }

    public function changeState($state): void
    {
        $this->state = $state;
        $this->stateTimeStart = microtime(true);
    }

    /**
     * 获取了状态储蓄了多少时间（精确到毫秒）
     *
     * @return float
     */
    public function getStateDuration(): float
    {
        return floatval(bcsub(microtime(true), $this->stateTimeStart, 4));
    }

    /**
     * 判断Connection是否处于某个状态并持续了x秒
     *
     * @param int $state
     * @param float $duration_seconds
     * @return boolean
     */
    public function stayOver($state, float $duration_seconds): bool
    {
        return ($this->inState($state))
                ? $this->getStateDuration() > $duration_seconds
                : false;
    }

    public function getTransaction(): Transaction
    {
        return $this->transaction;
    }

    public function inTransaction(): bool
    {
        return null !== $this->transaction;
    }

    public function closeTransanction()
    {
        $this->transaction = null;
        $this->changeState(self::STATE_IDLE);
    }

    public function beginTransaction(Transaction $transaction)
    {
        $this->transaction = $transaction;
    }

    public function query(string $sql, array $params = []): PromiseInterface
    {
        if (false === $this->inState(self::STATE_IDLE)) {
            return reject(new DatabaseException('Connection is not ready for query yet'));
        }

        if ($params) {
            $sql = $this->bindParamsToSql($sql, $params);
        }

        $this->deferred = new Deferred(function () {
            $this->close();
            throw new DatabaseException("Query has been cancelled due to promise-cancelling");
        });
        $this->changeState(self::STATE_WORKING);
        $this->link->query($sql, MYSQLI_ASYNC);
        return $this->deferred->promise();
    }

    public function resolve($mixed)
    {
        $this->deferred->resolve($this->fetchQueryResult($mixed));
        $this->reset();
    }

    public function reject($error)
    {
        $this->deferred->reject($error);
        $this->reset();
    }

    private function fetchQueryResult($mixed)
    {
        if ($mixed instanceof mysqli_result) {
            $data = $mixed->fetch_all(MYSQLI_ASSOC);
            $mixed->close();
            return QueryResult::useQueryResult($data);
        } else {
            return QueryResult::useExecutionResult($this->link->insert_id, $this->link->affected_rows);
        }
    }

    private function reset()
    {
        $this->deferred = null;
        $this->changeState(self::STATE_IDLE);
    }

    private function bindParamsToSql(string $sql, array $params)
    {
        $search = [];
        $replace = [];
        foreach ($params as $key => $val) {
            if ($val instanceof Expression) {
                $val = strval($val);
            } elseif (is_string($val)) {
                $escaped = $this->link->real_escape_string($val);
                $val = "'{$escaped}'";
            }
            $search[] = $key;
            $replace[] = $val;
        }
        return str_replace($search, $replace, $sql);
    }

    /**
     * 更新概述
     *
     * @return void
     */
    private function updateProfile()
    {
        $param = func_get_args();
        if (count($param) == 1) {
            foreach ($param as $key => $val) {
                $this->profile[$key] = $val;
            }
        } else {
            $this->profile[$param[0]] = $param[1];
        }
        $this->profile['time_updated'] = microtime(true);
    }
}
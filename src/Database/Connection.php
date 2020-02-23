<?php

namespace Lightning\Database;

use mysqli;
use mysqli_result;
use Throwable;
use RuntimeException;
use Lightning\Database\Expression;
use Lightning\Database\QueryResult;
use function Lightning\container;
use React\Promise\{Deferred, PromiseInterface};
use function React\Promise\{reject};

class Connection
{
    const STATE_CLOSE = -1;
    const STATE_IDLE = 1;
    const STATE_WORKING = 2;
    const STATE_TRANSACTION_IDLE = 4;
    const STATE_TRANSACTION_WORKING = 8;

    const ACTION_STATE_CHANGED = 'state_changed';

    const FETCH_MODES = ['fetch_all', 'fetch_row'];

    private $eventManager;
    private $connectionName;
    private $role;
    /** @var mysqli $link */
    private $link;
    private $credential = [];
    private $profile = [];
    private $state = self::STATE_CLOSE;
    private $stateTimeStart = 0;
    private $deferred;
    private $fetchMode;
    private $transaction = null;

    public function __construct(string $connection_name, array $options)
    {
        $this->eventManager = container()->get('event-manager');
        $this->connectionName = $connection_name;
        $this->role = $options['role'];
        unset($options['role']);
        $this->credential = $options;
        $this->updateProfile('time_created', microtime(true));
    }

    public function getLink(): ?mysqli
    {
        return $this->link;
    }

    public function getState()
    {
        return $this->state;
    }

    public function getStateDuration()
    {
        return floatval(bcsub(microtime(true), $this->stateTimeStart, 4));
    }

    public function beginTransaction(Transaction $transaction): self
    {
        $this->transaction = $transaction;
        $this->changeState(self::STATE_TRANSACTION_IDLE);
        return $this;
    }

    public function inTransaction(): bool
    {
        return $this->transaction !== null;
    }

    public function getTransaction(): ?Transaction
    {
        return $this->transaction;
    }

    public function closeTransaction()
    {
        $this->transaction = null;
        $this->changeState(self::STATE_IDLE);
    }

    public function query(string $sql, ?array $params = [], string $fetch_mode = 'fetch_row'): PromiseInterface
    {
        if (!in_array($this->state, [self::STATE_IDLE, self::STATE_TRANSACTION_IDLE])) {
            return reject(new RuntimeException('Connection is not ready for query yet'));
        }

        if (!empty($params)) {
            $sql = $this->bindParamsToSql($sql, $params);
        }
        
        $this->fetchMode = $fetch_mode;
        $this->updateProfile('time_query', time());
        $canceller = function () {
            $this->close();
            throw new DatabaseException("Query is cancelled");
        };
        $this->deferred = new Deferred($canceller);
        $state = $this->inTransaction() ? self::STATE_TRANSACTION_WORKING : self::STATE_WORKING;
        $this->changeState($state);
        $this->link->query($sql, MYSQLI_ASYNC);
        return $this->deferred->promise();
    }

    public function resolve($mixed)
    {
        if (null !== $this->deferred) {
            $this->deferred->resolve($this->fetchQueryResult($mixed));
        }
        $this->reset();
        $state = ($this->inTransaction()) ? self::STATE_TRANSACTION_IDLE : self::STATE_IDLE;
        $this->changeState($state);
    }

    public function reject($error)
    {
        if (null !== $this->deferred) {
            $this->deferred->reject($error);
        }
        $this->reset();
        if ($this->inTransaction()) {
            $this->getTransaction()->rollback();
            $this->changeState(self::STATE_TRANSACTION_IDLE);
        } else {
            $this->changeState(self::STATE_IDLE);
        }
    }

    public function terminate($error)
    {
        if (null !== $this->deferred) {
            $this->deferred->reject($error);
        }
        $this->reset();
        $this->close();
    }

    public static function eventName(string $action, $state): string
    {
        $class_name = str_replace("\\", "_", self::class);
        return implode('.', [$class_name, $action, $state]);
    }

    public function open(): bool
    {
        if ($this->state !== self::STATE_CLOSE) {
            return false;
        }

        $this->updateProfile('time_opened', time());
        $config = $this->credential;
        try {
            $this->link = new mysqli(
                $config['host'],
                $config['username'],
                $config['password'],
                $config['dbname'],
                $config['port']
            );
            $this->changeState(self::STATE_IDLE);
            return true;
        } catch (Throwable $e) {
            //do log stuff
            echo json_encode([
                'code' => $e->getCode(),
                'msg' => $e->getMessage(),
                'config' => $config
            ]) . "\r\n";
            return false;
        }
    }

    public function close()
    {
        if (null !== $this->link) {
            $this->link->kill($this->link->thread_id);
            $this->link->close();
            $this->link = null;
        }
        $this->reset();
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

    public function getProfile($field)
    {
        return isset($this->profile[$field]) ? $this->profile[$field] : null;
    }

    private function reset()
    {
        $this->deferred = null;
        $this->transaction = null;
        $this->fetchMode = '';
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

    private function fetchQueryResult($mixed): QueryResult
    {
        if ($mixed instanceof mysqli_result) {
            if ($this->fetchMode == 'fetch_row') {
                $data = $mixed->fetch_assoc();
            } elseif ($this->fetchMode == 'fetch_all') {
                $data = $mixed->fetch_all(MYSQLI_ASSOC);
            }
            $mixed->close();
            return QueryResult::setQueryResult($data);
        } else {
            return QueryResult::setExecutionResult($this->link->insert_id, $this->link->affected_rows);
        }
    }

    private function changeState($state)
    {
        $this->state = $state;
        $this->stateTimeStart = microtime(true);
        $this->eventManager->emit(
            self::eventName(self::ACTION_STATE_CHANGED, $state),
            [
                'connection_name' => $this->connectionName,
                'role' => $this->role,
                'connection' => $this
            ]
        );
    }

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
        $this->profile['time_updated'] = time();
    }
}

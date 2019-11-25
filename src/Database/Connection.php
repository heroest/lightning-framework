<?php
namespace Lightning\Database;

use mysqli;
use Throwable;
use RuntimeException;
use Lightning\Database\QueryResult;
use function Lightning\{getObjectId, container};
use React\Promise\{Deferred, PromiseInterface};

class Connection
{
    const STATE_CLOSE = -1;
    const STATE_IDLE = 1;
    const STATE_WORKING = 2;
    const STATE_OCCUPY = 4;

    const ACTION_STATE_CHANGED = 'state_changed';


    private $eventManager;
    private $connectionName;
    private $role;
    private $link;
    private $credential = [];
    private $profile = [];
    private $state = self::STATE_CLOSE;
    private $stateTimeStart = 0;
    private $deferred;
    private $fetchRow;

    public function __construct(string $connection_name, array $options)
    {
        $this->eventManager = container()->get('event-manager');
        $this->connectionName = $connection_name;
        $this->role = $options['role'];
        $this->credential = $options;
        $this->updateProfile('time_created', time());
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

    public function query(string $sql, bool $fetch_row = false): PromiseInterface
    {
        if ($this->state !== self::STATE_IDLE) {
            throw new RuntimeException('Connection is not ready for query yet');
        }

        $this->fetchRow = $fetch_row;
        $this->updateProfile('time_query', time());
        $this->deferred = new Deferred();
        $this->state = self::STATE_WORKING;
        $this->link->query($sql, MYSQLI_ASYNC);
        return $this->deferred->promise();
    }

    public function resolve(QueryResult $query_result)
    {
        if ($this->deferred !== null) {
            if ($this->fetchRow) {
                $query_result->data = current($query_result->result);
            }
            $this->deferred->resolve($query_result);
            $this->deferred = null;
            $this->fetchRow = false;
            $this->state = self::STATE_IDLE;
            $this->notifyStateChange(self::STATE_IDLE);
        }
    }

    public function reject(Throwable $error)
    {
        if ($this->deferred !== null) {
            $this->deferred->reject($error);
            $this->deferred = null;
            $this->fetchRow = false;
            $this->state = self::STATE_IDLE;
            $this->notifyStateChange(self::STATE_IDLE);    
        }
    }

    public static function eventName(string $action, $state): string
    {
        return implode('.', ['connection', $action, $state]);
    }

    public function open(): bool
    {
        if ($this->link !== null) {
            $this->link->close();
            $this->link = null;
        }

        $this->state = self::STATE_IDLE;
        $this->updateProfile('time_opened', time());
        $config = $this->credential;
        try {
            $this->link = new mysqli(
                $config['host'],
                $config['username'],
                $config['password'],
                $config['dbname']
            );
            $this->notifyStateChange(self::STATE_IDLE);
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    public function close()
    {
        if ($this->link !== null) {
            $this->link->close();
            $this->link = null;
        }

        $this->state = self::STATE_CLOSE;
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

    private function notifyStateChange($state)
    {
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
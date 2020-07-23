<?php 

namespace Lightning\Database;

use Lightning\Database\{QueryManager, Transaction};
use React\Promise\PromiseInterface;

class Query
{
    const TYPE_SELECT = 'select';
    const TYPE_INSERT = 'insert';
    const TYPE_UPDATE = 'update';
    const TYPE_DELETE = 'delete';

    const FETCH_ALL = 'fetch_all';
    const FETCH_ROW = 'fetch_row';

    /** @var QueryManager $qm */
    private $qm;
    private $queryType = self::TYPE_SELECT;
    private $connectionName = '';
    private $connectionRole = '';
    private $transaction = null;
    private $maxExectionTime = 29.07;

    private $sql = '';
    private $params = [];

    private $select = ['*'];
    private $from = '';
    private $where = [];
    private $join = [];
    private $sets = []; //for-update
    private $keys = [];
    private $values = [];
    private $offset = 0;
    private $take = 0;
    private $orderBy = '';

    private function __construct(string $connection_name = '', string $connection_role, Transaction $transaction = null)
    {
        $this->qm = QueryManager::getInstance();
        if (null !== $transaction) {
            $this->transaction = $transaction;
            $this->connectionName = $transaction->getConnectionName();
            $this->connectionRole = 'master';
        } else {
            $this->connectionName = $connection_name;
            if ($connection_role and in_array($connection_role, ['master', 'slave'])) {
                $this->connectionRole = strtolower($connection_role);
            }
        }
    }

    public static function useConnection(string $connection_name, string $connection_role = '')
    {
        return new self($connection_name, $connection_role);
    }

    public static function useTransaction(Transaction $transaction)
    {
        if ($transaction->isClosed()) {
            throw new DatabaseException("transaction is closed");
        }
        return new self('', '', $transaction);
    }

    public function getConnectionName(): string
    {
        return $this->connectionName;
    }

    public function getConnectionRole(): string
    {
        return $this->connectionRole;
    }

    public function getTransaction(): ?Transaction
    {
        return $this->transaction;
    }

    public function getMaxExecutionTime(): float
    {
        return $this->maxExectionTime;
    }

    public function getSql(): string
    {
        return $this->sql;
    }

    public function getParams(): array
    {
        return $this->params;
    }

    public function setSql(string $sql): self
    {
        $this->sql = $sql;
        return $this;
    }

    public function setParams(array $params): self
    {
        $this->params = $params;
        return $this;
    }

    public function one(): PromiseInterface
    {
        $this->connectionRole = $this->connectionRole ?: 'slave';
        return self::fetchResultPromise($this->qm->execute($this), self::FETCH_ROW);
    }

    public function all(): PromiseInterface
    {
        $this->connectionRole = $this->connectionRole ?: 'slave';
        return self::fetchResultPromise($this->qm->execute($this), self::FETCH_ALL);
    }

    public function execute(): PromiseInterface
    {
        $this->connectionRole = $this->connectionRole ?: 'master';
        return self::fetchResultPromise($this->qm->execute($this));
    }

    private static function fetchResultPromise($promise, $fetch_type = ''): PromiseInterface
    {
        return $promise->then(
            function (QueryResult $result) use ($fetch_type) {
                switch ($fetch_type) {
                    case self::FETCH_ROW: return current($result->data);
                    case self::FETCH_ALL: return $result->data;
                    default: return $result;
                }
            },
            function ($error) {
                throw $error;
            }
        );
    }
}
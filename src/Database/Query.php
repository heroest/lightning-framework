<?php

namespace Lightning\Database;

use React\Promise\PromiseInterface;
use Lightning\Database\QueryComponent\AbstractComponent;
use Lightning\Database\{Connection, Transaction};
use Lightning\Exceptions\DatabaseException;
use Lightning\Database\QueryResolver;
use function Lightning\{container, config};

class Query
{
    const TYPE_SELECT = 'select';
    const TYPE_INSERT = 'insert';
    const TYPE_UPDATE = 'update';
    const TYPE_DELETE = 'delete';

    private $queryType = self::TYPE_SELECT;
    private $resolver;
    private $fetchMode = 'fetch_all';
    private $connectionName = '';
    private $connectionRole = '';
    private $transaction = null;
    private $maxExectionTime = 0;

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

    private function __construct(string $connection_name = '', string $connection_role = '', Transaction $transaction = null)
    {
        if (null !== $transaction) {
            $this->transaction = $transaction;
            $this->connectionName = $transaction->getConnectionName();
            $this->connectionRole = 'master';
        } else {
            $this->connectionName = $connection_name;
            $this->connectionRole = $connection_role;
        }
        $this->resolver = new QueryResolver();
        $this->maxExectionTime = config()->get('database.max_exection_time', 300);
    }

    public static function useConnection(string $connection_name, string $connection_role = ''): self
    {
        return new self($connection_name, $connection_role);
    }

    public static function useTransaction(Transaction $transaction): self
    {
        return new self('', '', $transaction);
    }

    public function inTransaction()
    {
        return $this->transaction !== null;
    }

    public function getConnectionName()
    {
        return $this->connectionName;
    }

    public function getConnectionRole()
    {
        return $this->connectionRole;
    }

    public function getTransaction()
    {
        return $this->transaction;
    }

    public function getFetchMode()
    {
        return $this->fetchMode;
    }

    public function getSql(): string
    {
        return $this->sql;
    }

    public function getParams(): array
    {
        return $this->params;
    }

    public function getMaxExecutionTime(): float
    {
        return $this->maxExectionTime;
    }

    public function setSql(string $sql)
    {
        $this->sql = $sql;
        return $this;
    }

    public function setParams(array $params)
    {
        $this->params = $params;
        return $this;
    }

    public function setFetchMode(string $fetch_mode)
    {
        if (!in_array($fetch_mode, Connection::FETCH_MODES)) {
            throw new DatabaseException("Unknown Fetch Modes: {$fetch_mode}");
        }
        $this->fetchMode = $fetch_mode;
        return $this;
    }

    public function setMaxExecutionTime(float $time)
    {
        $this->maxExecutionTime = $time;
        return $this;
    }

    public function from(string $table_name)
    {
        $this->from = $table_name;
        return $this;
    }

    public function select(array $select, bool $append = false)
    {
        $this->queryType = self::TYPE_SELECT;
        $this->select = $append ? array_merge($this->select, $select) : $select;
        return $this;
    }

    public function where()
    {
        $this->baseWhere('AND', func_get_arg(0));
        return $this;
    }

    public function orWhere()
    {
        $this->baseWhere('OR', func_get_arg(0));
        return $this;
    }

    public function exists()
    {
        $this->baseWhere('AND', func_get_arg(0));
        return $this;
    }

    public function orExists()
    {
        $this->baseWhere('OR', func_get_arg(0));
        return $this;
    }

    public function join()
    {
    }

    public function limit()
    {
        list($offset, $take) = $this->resolver->resolveLimit(func_get_args());
        $this->offset = $offset;
        $this->take = $take;
        return $this;
    }

    public function orderBy($clause)
    {
        $this->orderBy = $this->resolver->resolveOrderBy($clause);
        return $this;
    }

    public function groupBy()
    {
    }

    public function update()
    {
        $this->setDefaultRole('master');
        $this->queryType = self::TYPE_UPDATE;
    }

    public function insert()
    {
        $this->setDefaultRole('master');
        $this->queryType = self::TYPE_INSERT;
    }

    public function one(): PromiseInterface
    {
        $this->setDefaultRole('slave');
        $this->queryType = self::TYPE_SELECT;
        $this->offset = 0;
        $this->take = 1;
        $this->fetchMode = 'fetch_row';
        $this->compile();

        $dbm = container()->get('dbm');
        return self::fetchResultPromise($dbm->execute($this));
    }

    public function all(): PromiseInterface
    {
        $this->setDefaultRole('slave');
        $this->queryType = self::TYPE_SELECT;
        $this->fetchMode = 'fetch_all';
        $this->compile();

        $dbm = container()->get('dbm');
        return self::fetchResultPromise($dbm->execute($this));
    }

    public function execute(string $sql = '', array $params = [], string $fetch_mode = 'fetch_all'): PromiseInterface
    {
        if (!empty($sql)) {
            $this->setSql($sql);
        }
        if (!empty($params)) {
            $this->setParams($params);
        }
        if (!empty($fetch_mode)) {
            $this->setFetchMode($fetch_mode);
        }
        $dbm = container()->get('dbm');
        return $dbm->execute($this);
    }

    public function compile(bool $rebuild = false): void
    {
        if (!$rebuild and !empty($this->sql)) {
            return;
        }

        $components = [];
        switch ($this->queryType) {
            case self::TYPE_SELECT:
                $components = $this->compileSelectQuery();
                break;
            case self::TYPE_INSERT:
                $components = $this->compileInsertQuery();
                break;
            case self::TYPE_UPDATE:
                $components = $this->compileUpdateQuery();
                break;
            case self::TYPE_DELETE:
                $components = $this->compileDeleteQuery();
                break;
        }

        $compiled = [];
        $params = [];
        foreach ($components as $item) {
            if (is_string($item)) {
                $compiled[] = $item;
            } elseif ($item instanceof AbstractComponent) {
                $compiled[] = $item->getStatement();
                $params = array_merge($params, $item->getParams());
            }
        }
        $this->sql = implode(' ', $compiled);
        $this->params = $params;
    }

    private function setDefaultRole(string $role_name): void
    {
        if (empty($this->connectionRole)) {
            $this->connectionRole = $role_name;
        }
    }

    private function baseWhere(string $word, $params): void
    {
        if (0 < count($this->where)) {
            $this->where[] = $word;
        }
        $this->where = array_merge($this->where, $this->resolver->resolveWhere($params));
    }

    private function compileSelectQuery()
    {
        $components = [];
        $components[] = "SELECT";
        $components[] = implode(',', $this->select);
        $components[] = "FROM";
        $components[] = $this->from;

        if (!empty($this->where)) {
            $components[] = "WHERE";
            $components = array_merge($components, $this->where);
        }

        if (!empty($this->orderBy)) {
            $components[] = "ORDER BY {$this->orderBy}";
        }

        if ($this->take > 0) {
            $components[] = $this->offset === 0
                ? "LIMIT {$this->take}"
                : "LIMIT {$this->offset}, {$this->take}";
        }

        return $components;
    }

    private function compileUpdateQuery()
    {
    }

    private function compileInsertQuery()
    {
    }

    public function compileDeleteQuery()
    {
    }

    private static function fetchResultPromise(PromiseInterface $promise)
    {
        return $promise->then(function ($query_result) {
            return $query_result->data;
        }, function ($error) {
            throw $error;
        });
    }
}

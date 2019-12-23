<?php
namespace Lightning\Database;

use Lightning\Database\QueryComponent\AbstractComponent;
use RuntimeException;
use Lightning\Database\QueryResolver;
use function Lightning\container;

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

    public function __construct(string $connection_name, string $connection_role = '')
    {
        $this->connectionName = $connection_name;
        $this->connectionRole = $connection_role;
        $this->resolver = new QueryResolver();
    }

    public static function connection(string $connection_name, string $connection_role = '')
    {
        return new self($connection_name, $connection_role);
    }

    public function getConnectionName()
    {
        return $this->connectionName;
    }

    public function getConnectionRole()
    {
        return $this->connectionRole;
    }

    public function getFetchMode()
    {
        return $this->fetchMode;
    }

    public function setSql(string $sql)
    {
        $this->sql = $sql;
        return $this;
    }

    public function getSql(): string
    {
        return $this->sql;
    }

    public function setParams(array $params)
    {
        $this->params = $params;
        return $this;
    }

    public function getParams(): array
    {
        return $this->params;
    }


    public function from(string $table_name)
    {
        $this->from = $table_name;
        return $this;
    }

    public function select(array $select, bool $append = false)
    {
        $this->queryType = self::TYPE_SELECT;
        $select = array_values($select);
        $this->select = $append ? array_merge($this->select, $select) : $select;
        return $this;
    }

    public function where()
    {
        $this->wrappedWhere('AND', func_get_arg(0));
        return $this;
    }

    public function orWhere()
    {
        $this->wrappedWhere('OR', func_get_arg(0));
        return $this;
    }

    public function exists()
    {
        $this->wrappedWhere('AND', func_get_arg(0));
        return $this;
    }

    public function orExists()
    {
        $this->wrappedWhere('OR', func_get_arg(0));
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

    public function orderBy()
    {

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

    public function one()
    {
        $this->setDefaultRole('slave');
        $this->queryType = self::TYPE_SELECT;
        $this->offset = 0;
        $this->take = 1;
        $this->fetchMode = 'fetch_row';
        $this->compile();

        $dbm = container()->get('dbm');
        return $dbm->runQuery($this);
    }

    public function all()
    {
        $this->setDefaultRole('slave');
        $this->queryType = self::TYPE_SELECT;
        $this->fetchMode = 'fetch_all';
        $this->compile();

        $dbm = container()->get('dbm');
        return $dbm->runQuery($this);
    }

    public function compile(bool $rebuild = false)
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

    private function setDefaultRole(string $role_name)
    {
        if (empty($this->connectionRole)) {
            $this->connectionRole = $role_name;
        }
    }
    
    private function wrappedWhere(string $word, $params)
    {
        if (0 === count($this->where)) {
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
            $components = array_merge($components, array_values($this->where));
        }
        if (!empty($this->take)) {
            if (empty($this->offset)) {
                $components[] = "LIMIT {$this->take}";
            } else {
                $components[] = "LIMIT {$this->offset}, {$this->take}";
            }
        }
        return $components;
    }
}
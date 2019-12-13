<?php
namespace Lightning\Database;

use RuntimeException;
use Lightning\Database\QueryResolver;
use function Lightning\container;

class Query
{
    const TYPE_SELECT = 'select';
    const TYPE_INSERT = 'insert';
    const TYPE_UPDATE = 'update';

    private $queryType = self::TYPE_SELECT;
    private $resolver;
    private $fetchMode = 'fetch_all';
    private $sql = '';
    private $connectionName = '';
    private $connectionRole = '';
    private $select = ['*'];
    private $where = [];
    private $join = [];
    private $values = []; //for-update

    private function __construct(string $connection_name, string $connection_role = 'slave')
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

    public function getSql()
    {
        return $this->sql;
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
        $is_first = (0 === count($this->where)) ? true : false;
        if (! $is_first) {
            $this->where[] = 'AND';
            $this->where[] = '(';
        }
        $this->where = array_merge($this->where, QueryResolver::resolveWhere(func_get_arg(0)));
        if (! $is_first) {
            $this->where[] = ')';
        }
        return $this;
    }

    public function orWhere()
    {
        $is_first = (0 === count($this->where)) ? true : false;
        if (! $is_first) {
            $this->where[] = 'OR';
            $this->where[] = '(';
        }
        $this->where = array_merge($this->where, QueryResolver::resolveWhere(func_get_arg(0)));
        if (! $is_first) {
            $this->where[] = ')';
        }
        return $this;
    }

    public function exists()
    {

    }

    public function join()
    {

    }

    public function limit()
    {

    }

    public function orderBy()
    {

    }

    public function groupBy()
    {

    }

    public function update()
    {
        $this->queryType = self::TYPE_UPDATE;
    }

    public function insert()
    {
        $this->queryType = self::TYPE_INSERT;
    }

    public function one()
    {

    }

    public function all()
    {

    }

    private function execute()
    {
        if ($this->sql === '') {
            $this->compile();
        }
        $dbm = container()->get('dbm');
    }

    private function compile()
    {

    }
}
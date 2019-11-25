<?php
namespace Hereost\Console\Database;

use RuntimeException;

class Query
{
    private $select = ['*'];
    private $where = [];
    private $leftJoin = [];
    private $rightJoin = [];
    private $innerJoin = [];

    public function __construct()
    {

    }

    public function select(array $select)
    {
        $this->select = $select;
        return $this;
    }

    public function where()
    {
        $params = func_get_args();
    }
}
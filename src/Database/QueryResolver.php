<?php
namespace Lightning\Database;

use function Lightning\isAssoc;
use Lightning\Database\QueryComponent\Where;
use InvalidArgumentException;

class QueryResolver
{
    private $index = 0;

    public function resolveWhere($param): array
    {
        $components = [];
        if (is_string($param)) {
            $components[] = $param;
        } elseif (isAssoc($param)) {
            $is_first = true;
            foreach ($param as $key => $val) {
                if (true === $is_first) {
                    $is_first = false;
                } else {
                    $components[] = 'AND';
                }
                $components[] = new Where($key, '=', [$val], ++$this->index);
            }
        } else {
            $first = array_shift($param);
            if (! is_string($first)) {
                throw new InvalidArgumentException("1st parameter expected to be string, but {gettype($first)} given");
            }
            $first = strtoupper($first);
            if (in_array($first, ['IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN'])) {
                $components[] = new Where($param[0], $first, $param[1], ++$this->index);
            } elseif (in_array($first, ['AND', 'OR'])) {
                $components[] = '(';
                $is_first = true;
                foreach ($param as $row) {
                    if (! is_array($row)) {
                        throw new InvalidArgumentException("parameter exptected to be array, but {gettype($row)} given");
                    }
                    if (true === $is_first) {
                        $is_first = false;
                    } else {
                        $components[] = $first;
                        $components = array_merge($components, self::resolveWhere($row));
                    }
                }
                $components[] = ')';
            } elseif (in_array($first, ['EXISTS', 'NOT EXISTS'])) {
                $components[] = new Where('', $first, $param[0], ++$this->index);
            } else {
                $components[] = new Where($param[0], $first, $param[1], ++$this->index);
            }
        }
        return $components;
    }

    public function resolveJoin(array $params): array
    {
        $components = [];
        return $components;
    }

    public function resolveOrderBy(array $params): array
    {
        $components = [];
        return $components;
    }

    public function resolveGroupBy(array $params): array
    {
        $components = [];
        return $components;
    }

    public function resolveInsert(array $params): array
    {
        $components = [];
        return $components;
    }

    public function resolveUpdate(array $params): array
    {
        $components = [];
        return $components;
    }
}
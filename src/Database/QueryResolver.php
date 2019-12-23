<?php
namespace Lightning\Database;

use function Lightning\isAssoc;
use Lightning\Database\QueryComponent\Where;
use InvalidArgumentException;

class QueryResolver
{
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
                $components[] = new Where($key, '=', [$val]);
            }
        } else {
            $first = array_shift($param);
            if (!is_string($first)) {
                throw new InvalidArgumentException("1st parameter expected to be string, but {gettype($first)} given");
            }
            $_first = strtoupper($first);
            if (in_array($_first, ['IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN'])) {
                $components[] = new Where($param[0], $_first, $param[1]);
            } elseif (in_array($_first, ['AND', 'OR'])) {
                $is_first = true;
                foreach ($param as $row) {
                    if (!is_array($row)) {
                        throw new InvalidArgumentException("parameter exptected to be array, but {gettype($row)} given");
                    }
                    if (true === $is_first) {
                        $is_first = false;
                    } else {
                        $components[] = $_first;
                    }
                    $components[] = '(';
                    $components = array_merge($components, $this->resolveWhere($row));
                    $components[] = ')';
                }
            } elseif (in_array($_first, ['EXISTS', 'NOT EXISTS'])) {
                $components[] = new Where('', $_first, [$param[0]]);
            } else {
                $components[] = new Where($first, $param[0], [$param[1]]);
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

    public function resolveLimit(array $params): array
    {
        $offset = 0;
        $take = 0;

        if (2 === count($params)) {
            list($offset, $take) = $params;
        } else {
            $take = $params[0];
        }
        return [$offset, $take];
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
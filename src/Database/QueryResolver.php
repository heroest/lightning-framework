<?php
namespace Lightning\Database;

use function Lightning\isAssoc;
use Lightning\Database\QueryComponent\Where;
use Lightning\Database\Expression;
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
            $op = array_shift($param);
            if (!is_string($op)) {
                throw new InvalidArgumentException("1st parameter expected to be string, but {gettype($op)} given");
            }
            $op = strtoupper($op);
            if (in_array($op, ['IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN'])) {
                $components[] = new Where($param[0], $op, $param[1]);
            } elseif (in_array($op, ['AND', 'OR'])) {
                $is_first = true;
                foreach ($param as $row) {
                    if (!is_array($row)) {
                        throw new InvalidArgumentException("parameter exptected to be array, but {gettype($row)} given");
                    }
                    if (true === $is_first) {
                        $is_first = false;
                    } else {
                        $components[] = $op;
                    }
                    $components[] = '(';
                    $components = array_merge($components, $this->resolveWhere($row));
                    $components[] = ')';
                }
            } elseif (in_array($op, ['EXISTS', 'NOT EXISTS'])) {
                $components[] = new Where('', $op, [$param[0]]);
            } else {
                $components[] = new Where($param[0], $op, [$param[1]]);
            }
        }
        return $components;
    }

    public function resolveJoin(array $params): array
    {
        $components = [];
        return $components;
    }

    public function resolveOrderBy($line): string
    {
        if ($line instanceof Expression) {
            return $line;
        }

        $result = [];
        $pattern = "#([^\s\.`]{1,})\.?([^\s\.`]*)`?\s*(ASC|DESC)?#i";
        foreach (explode(',', $line) as $item) {
            $matches = [];
            $block = [];
            $res = preg_match($pattern, $item, $matches);
            if (empty($res)) {
                throw new InvalidArgumentException("Unable to resove orderBy statement: {$line}");
            }
            $table = $matches[1];
            $column = $matches[2];
            $order = $matches[3];

            $block[] = "`{$table}`";
            if (!empty($column)) {
                $block[] = ".`{$column}`";
            }
            if (empty($order)) {
                $block[] = ' ASC';
            } else {
                $block[] = ' ' . strtoupper($order);
            }
            $result[] = implode('', $block);
        }
        return implode(',', $result);
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
<?php

namespace Lightning\Database;

class QueryResult
{
    public $data = [];
    public $lastInsertedId = 0;
    public $numRowAffected = 0;

    private function __construct(array $data = [], $last_insert_id = 0, $num_row_affected = 0)
    {
        $this->data = $data;
        $this->lastInsertId = $last_insert_id;
        $this->numRowAffected = $num_row_affected;
    }

    public static function useQueryResult($result): self
    {
        return new self($result);
    }

    public static function useExecutionResult($last_insert_id, $num_row_affected): self
    {
        return new self([], $last_insert_id, $num_row_affected);
    }
}
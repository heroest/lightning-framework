<?php
namespace Lightning\Database;

use Throwable;
class QueryResult
{
    public $data = [];
    public $lastInsertId = 0;
    public $numRowAffected = 0;

    private function __construct(?array $data = null, $last_insert_id = 0, $num_row_affected = 0)
    {
        $this->data = $data;
        $this->lastInsertId = $last_insert_id;
        $this->numRowAffected = $num_row_affected;
    }

    public static function setQueryResult($result): self
    {
        return new self($result);
    }

    public static function setExecutionResult($last_insert_id, $num_row_affected): self
    {
        return new self(null, $last_insert_id, $num_row_affected);
    }
}
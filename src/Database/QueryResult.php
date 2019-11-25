<?php
namespace Lightning\Database;

class QueryResult
{
    public $result = [];
    public $lastInsertId = 0;
    public $numRowAffected = 0;

    public function __construct($result = null, $last_insert_id = 0, $num_row_affected = 0)
    {
        $this->result = $result;
        $this->lastInsertId = $last_insert_id;
        $this->numRowAffected = $num_row_affected;
    }
}
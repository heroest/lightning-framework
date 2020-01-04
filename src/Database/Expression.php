<?php
namespace Lightning\Database;

class Expression
{
    private $string = '';

    public function __construct(string $string)
    {
        $this->string = $string;
    }

    public function __toString()
    {
        return $this->string;
    }
}
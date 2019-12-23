<?php
namespace Lightning\Database\QueryComponent;

use Lightning\Database\QueryComponent\AbstractComponent;
use Lightning\Database\Query;

class Where extends AbstractComponent
{
    private $statement = '';
    private $params = [];

    private $key = '';
    private $op = '';
    private $values = [];

    public function __construct(string $key, string $op, array $values)
    {
        $this->key = $key;
        $this->op = $op;
        $this->values = $values;
        $this->buildStatement();
    }

    public function getStatement(): string
    {
        return $this->statement;
    }

    public function getParams(): array
    {
        return $this->params;
    }

    private function buildStatement()
    {
        if (in_array($this->op, ['IN', 'NOT IN'])) {
            $phs = [];
            foreach ($this->values as $value) {
                $ph = $this->nextph();
                $phs[] = $ph;
                $this->params[$ph] = $value;
            }
            $stmt = implode(',', $phs);
            $this->statement = "{$this->key} {$this->op} ({$stmt})";
            return;
        } elseif (in_array($this->op, ['BETWEEN', 'NOT BETWEEN'])) {
            $lph = $this->nextph();
            $rph = $this->nextph();
            $this->params[$lph] = $this->values[0];
            $this->params[$rph] = $this->values[1];
            $this->statement = "{$this->key} {$this->op} ({$lph} AND {$rph})";
            return;
        } elseif (in_array($this->op, ['EXISTS', 'NOT EXISTS'])) {
            $ph = $this->nextph();
            $value = $this->values[0];
            if ($value instanceof Query) {
                $value->compile();
                $sub = $value->getSql();
                $this->statement = "{$this->op} ($sub)";
                $this->params = array_merge($this->params, $value->getParams());
            } else {
                $this->params[$ph] = $value;
                $this->statement = "{$this->op} ({$ph})";
            }
            return;
        } else {
            $ph = $this->nextph();
            $this->params[$ph] = $this->values[0];
            $this->statement = "{$this->key} {$this->op} {$ph}";
            return;
        }
    }
}
<?php
namespace Lightning\Database\QueryComponent;

use Lightning\Database\QueryComponent\ComponentInterface;

class Where implements ComponentInterface
{
    private $key = '';
    private $op = '';
    private $values = [];
    private $params = [];
    private $step = 1;
    private $prefix;

    public function __construct(string $key, string $op, array $values, int $prefix)
    {
        $this->key = $key;
        $this->op = $op;
        $this->values = $values;
        $this->prefix = $prefix;
    }

    public function __toString()
    {
        if (in_array($this->op, ['IN', 'NOT IN'])) {
            $phs = [];
            foreach ($this->values as $value) {
                $ph = $this->key2ph();
                $phs[] = $ph;
                $this->params[$ph] = $value;
            }
            $stmt = implode(',', $phs);
            return "{$this->key} {$this->op} ({$stmt})";
        } elseif (in_array($this->op, ['BETWEEN', 'NOT BETWEEN'])) {
            $lph = $this->key2ph();
            $rph = $this->key2ph();
            $this->params[$lph] = $this->values[0];
            $this->params[$rph] = $this->values[1];
            return "{$this->key} {$this->op} ({$lph} AND {$rph})";
        } elseif (in_array($this->op, ['EXISTS', 'NOT EXISTS'])) {
            $ph = $this->key2ph();
            $this->params[$ph] = $this->values[0];
            return "{$this->op} {$ph}";
        } else {
            $ph = $this->key2ph();
            $this->params[$ph] = $this->values[0];
            return "{$this->key} {$this->op} {$ph}";
        }
    }

    private function key2ph()
    {
        $this->step++;
        return "::{$this->key}_{$this->prefix}_{$this->step}::";
    }
}
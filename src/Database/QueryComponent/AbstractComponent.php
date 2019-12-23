<?php
namespace Lightning\Database\QueryComponent;

abstract class AbstractComponent
{
    protected $prefix = '';
    private $step = 0;

    public abstract function getStatement(): string;

    public abstract function getParams(): array;

    protected function nextph()
    {
        $prefix = ltrim(spl_object_hash($this), '0');
        $this->step++;
        return ":ph_{$prefix}{$this->step}";
    }
}
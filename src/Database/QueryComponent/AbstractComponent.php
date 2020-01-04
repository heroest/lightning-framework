<?php
namespace Lightning\Database\QueryComponent;

use function Lightning\getObjectId;
abstract class AbstractComponent
{
    private $step = 0;

    public abstract function getStatement(): string;

    public abstract function getParams(): array;

    /**
     * get unique placeholder
     *
     * @return string
     */
    protected function nextph()
    {
        $prefix = getObjectId($this);
        $this->step++;
        return ":ph_{$prefix}{$this->step}";
    }
}
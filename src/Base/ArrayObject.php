<?php
namespace Lightning\Base;

/**
 * Simple implementation of ArrayAccess and Iterator
 */
class ArrayObject implements \ArrayAccess, \Iterator, \Countable
{
    private $storage = [];
    private $position = 0;

    public function toArray(): array
    {
        return $this->storage;
    }

    public function isEmpty(): bool
    {
        return empty($this->storage);
    }

    public function push($item)
    {
        $this->storage[] = $item;
    }

    public function pop()
    {
        return array_pop($this->storage);
    }

    public function shuffle()
    {
        shuffle($this->storage);
    }

    public function offsetExists($offset)
    {
        return isset($this->storage[$offset]);
    }

    public function offsetGet($offset)
    {
        return isset($this->storage[$offset]) ? $this->storage[$offset] : null;
    }

    public function offsetSet($offset, $value)
    {
        $this->storage[$offset] = $value;
    }

    public function offsetUnset($offset)
    {
        unset($this->storage[$offset]);
    }

    public function current()
    {
        return current($this->storage);
    }

    public function key()
    {
        return key($this->storage);
    }

    public function next()
    {
        next($this->storage);
    }

    public function rewind()
    {
        reset($this->storage);
    }

    public function valid()
    {
        return key($this->storage) !== null;
    }

    public function count()
    {
        return count($this->storage);
    }
}
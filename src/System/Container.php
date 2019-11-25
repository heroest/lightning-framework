<?php
namespace Lightning\System;

use Lightning\Exceptions\SystemException;
use InvalidArgumentException;
use Closure;

class Container
{
    private static $instance;
    private $definitions = [];
    private $components = [];
    private $core = [];

    private function __construct() {}

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function set(string $name, $mixed, bool $is_core = false)
    {
        if (is_string($mixed) or ($mixed instanceof Closure)) {
            $this->definitions[$name] = $mixed;
        } elseif (is_object($mixed)) {
            $this->components[$name] = $mixed;
        } else {
            throw new InvalidArgumentException(__METHOD__ . ' 2nd parameter expect to string, closure or object');
        }

        if ($is_core) {
            $this->core[$name] = true;
        }
    }

    public function get(string $name)
    {
        if (isset($this->components[$name])) {
            return $this->components[$name];
        } elseif (isset($this->definitions[$name])) {
            return $this->createComponent($name);
        } else {
            throw new SystemException("Getting unknown components with name: {$name}");
        }
    }

    public function fresh(string $name)
    {
        if (isset($this->components[$name]) and isset($this->core[$name])) {
            throw new SystemException("Core Component {$name} has been initialized already");
        } elseif (isset($this->definitions[$name])) {
            return $this->createComponent($name);
        } else {
            throw new SystemException("Getting unknown definitions with name: {$name}");
        }
    }

    public function has(string $name): bool
    {
        return isset($this->components[$name]) or isset($this->definitions[$name]);
    }

    public function delete(string $name)
    {
        if (isset($this->core[$name])) {
            throw new SystemException("Component {$name} is registered as core component");
        }

        if (isset($this->definitions[$name])) {
            unset($this->definitions[$name]);
        }

        if (isset($this->components[$name])) {
            unset($this->components[$name]);
        }
    }

    private function createComponent(string $name)
    {
        if (is_string($this->definitions[$name])) {
            $class = $this->definitions[$name];
            $this->components[$name] = new $class;
        } else {
            $closure = $this->definitions[$name];
            $this->components[$name] = $closure();
        }
        return $this->components[$name];
    }
}
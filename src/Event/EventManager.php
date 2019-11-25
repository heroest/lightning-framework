<?php
namespace Lightning\Event;

use Symfony\Component\EventDispatcher\{EventDispatcher, Event};
use Throwable;

class EventManager
{
    private $dispatcher;

    public function __construct() 
    {
        $this->dispatcher = new EventDispatcher();
    }

    public function on(string $event_name, callable $callback): self
    {
        $this->dispatcher->addListener($event_name, $callback);
        return $this;
    }

    public function off(string $event_name, callable $callback): self
    {
        if ($this->dispatcher->hasListeners($event_name)) {
            $this->dispatcher->removeListener($event_name, $callback);
        }
        return $this;
    }

    public function hasListeners(string $event_name)
    {
        return $this->dispatcher->hasListeners($event_name);
    }

    public function emit(string $event_name, $data = null)
    {
        $event = new Event();
        $event->name = $event_name;
        $event->data = $data;
        foreach (self::fetchEventName($event_name) as $sub_name) {
            $this->dispatcher->dispatch($event, $sub_name);
        }
    }

    private static function fetchEventName(string $event_name): \Generator
    {
        if (false !== stripos($event_name, '*')) {
            yield $event_name;
        } else {
            $arr = explode('.', $event_name);
            $key = array_shift($arr);
            $last_index = count($arr) - 1;
            foreach ($arr as $index => $sub) {
                $key = "{$key}.{$sub}";
                if ($index !== $last_index) {
                    $wildcard = "{$key}.*";
                    yield $wildcard;
                } else {
                    yield $key;
                }
            }
        }
    }
}
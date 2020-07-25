<?php
namespace Lightning\Console;

use React\Promise\PromiseInterface;
use function Lightning\{loop, setTimeout};
use Lightning\Base\Application AS BaseApplication;
use BadMethodCallException;

class Application extends BaseApplication
{
    public function __construct()
    {
        $this->bootstrap();
    }
    
    public function run()
    {
        list($controller_name, $action_name, $param) = $this->fetchInput();
        $callback = function() use ($controller_name, $action_name, $param) {
            $conroller = new $controller_name();
            if (!method_exists($conroller, $action_name)) {
                throw new BadMethodCallException("Calling unknown method in {$controller_name}: {$action_name}");
            }

            $result = call_user_func_array([$conroller, $action_name], $param);
            if ($result === null) {
                return;
            } elseif ($result instanceof PromiseInterface) {
                $stopper = (function () {
                    loop()->stop();
                })->bindTo(null);
                $result->then($stopper, $stopper);
            } else {
                loop()->stop();
            }
        };
        setTimeout($callback->bindTo(null), 0);
        loop()->run();
    }

    private function fetchInput()
    {
        $input = $_SERVER['argv'];
        array_shift($input);
        $route = array_shift($input);
        list($controller_name, $action_name) = self::fetchControllerAction($route);
        return [$controller_name, $action_name, $input];
    }
}
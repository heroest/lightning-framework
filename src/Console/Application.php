<?php
namespace Lightning\Console;

use React\Promise\PromiseInterface;
use function Lightning\container;
use BadMethodCallException;

class Application extends \Lightning\Base\Application
{
    public function __construct()
    {
        $this->bootstrap();
    }
    
    public function run()
    {
        list($controller_name, $action_name, $param) = $this->fetchInput();
        $executor = function() use ($controller_name, $action_name, $param) {
            $conroller = new $controller_name();
            if (!method_exists($conroller, $action_name)) {
                throw new BadMethodCallException("Calling unknown method in {$controller_name}: {$action_name}");
            }

            $result = call_user_func_array([$conroller, $action_name], $param);
            if ($result === null) {
                return;
            } elseif ($result instanceof PromiseInterface) {
                $result->then(function() {
                    container()->get('loop')->stop();
                });
            } else {
                container()->get('loop')->stop();
            }
        };
        $loop = container()->get('loop');
        $loop->addTimer(0, $executor->bindTo(null));
        $loop->run();
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
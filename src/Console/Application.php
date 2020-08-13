<?php
namespace Lightning\Console;

use React\Promise\PromiseInterface;
use function Lightning\{loop, nextTick, co};
use Lightning\Coroutine\Coroutine;
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
            $controller = new $controller_name();
            if (!method_exists($controller, $action_name)) {
                throw new BadMethodCallException("Calling unknown method in {$controller_name}: {$action_name}");
            }
            $result = co([$controller, $action_name], $param);
            if ($result instanceof Coroutine) {
                $promise = $result->promise();
                $promise->then(
                    function ($response) {
                        if ($response) {
                            $response = is_string($response) ? $response : json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                            echo "{$response}\r\n";
                        }
                        loop()->stop();
                    },
                    function ($error) {
                        echo $error;
                        loop()->stop();
                    }
                );
            }
        };
        nextTick($callback->bindTo(null));
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
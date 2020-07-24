<?php

namespace Lightning\Web;

use Lightning\Web\{Input, Output};
use Lightning\Base\Application AS BaseApplication;
use Lightning\Coroutine\Coroutine;
use React\Promise\{PromiseInterface};
use React\Http\{Server, Response};
use React\Socket\Server as SocketServer;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Throwable;
use function Lightning\{container, config, setTimeout, clearTimer, loop, co, stopCo};


class Application extends BaseApplication
{
    public function __construct()
    {
        $this->bootstrap();
    }

    public function run(int $port = 80)
    {
        $loop = loop();
        $server = new Server([$this, 'handleRequest']);
        $server->listen(new SocketServer("0.0.0.0:{$port}", $loop));
        echo "sever listening on port: {$port}\r\n";
        $loop->run();
    }

    public function handleRequest(ServerRequestInterface $request)
    {
        $output = new Output();
        try {
            $callable = $this->fetchUrl($request->getUri()->getPath());   
            $coroutine = co($callable, [Input::parseRequest($request), $output]);
            return self::timeout($output, $coroutine);
        } catch (Throwable $e) {
            $output->setStatusCode($e->getCode() ?: 500)
                ->setFormat(Output::FORMAT_JSON)
                ->setContent([
                    'msg' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => explode("\n", $e->getTraceAsString())
                ])
                ->send();
            return $output->promise();
        }
    }

    private function fetchUrl(string $route): callable
    {
        $route = ltrim($route, '\//');
        if (empty($route)) {
            $route = 'index/index';
        } elseif (false === strpos($route, '/')) {
            $route = "{$route}/index";
        }
        list($controller_name, $action_name) = self::fetchControllerAction($route);

        if (!class_exists($controller_name)) {
            throw new RuntimeException("{$route} - Controller not found: {$controller_name}", 404);
        }

        $container = container();
        if (false === $container->has($controller_name)) {
            $container->set($controller_name, $controller_name);
        }
        $controller = $container->get($controller_name);

        if (!method_exists($controller, $action_name)) {
            throw new RuntimeException("{$route} - Action not found: {$action_name}", 404);
        }
        return [$controller, $action_name];
    }

    private static function timeout(Output $output, ?Coroutine $coroutine = null): PromiseInterface
    {
        $timer = setTimeout(function () use ($output, $coroutine) {
            $output->setFormat(Output::FORMAT_JSON)
                ->setContent(['error' => 'The connection is timeout'])
                ->setStatusCode(400)
                ->send();
            if (null !== $coroutine) {
                stopCo($coroutine);
            }
        }, config()->get('web.timeout', 30));
        return $output->promise()->then(function ($response) use ($timer) {
            clearTimer($timer);
            return $response;
        });
    }
}

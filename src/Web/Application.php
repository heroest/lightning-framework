<?php

namespace Lightning\Web;

use function Lightning\{container, loop, config};
use Lightning\Web\Output;
use React\Promise\{PromiseInterface};
use React\Http\{Server, Response};
use React\Socket\Server as SocketServer;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Throwable;


class Application extends \Lightning\Base\Application
{
    public function __construct()
    {
        $this->bootstrap();
    }

    public function run(int $port = 80)
    {
        $loop = container()->get('loop');
        $server = new Server([$this, 'handleRequest']);
        $server->listen(new SocketServer("0.0.0.0:{$port}", $loop));
        echo "sever listening on port: {$port}\r\n";
        $loop->run();
    }

    public function handleRequest(ServerRequestInterface $request)
    {
        try {
            $callable = $this->fetchUrl($request->getUri()->getPath());
            $output = new Output();
            call_user_func_array($callable, [$request, $output]);
            return self::timeout($output);
        } catch (Throwable $e) {
            $code = $e->getCode();
            return new Response(
                empty($code) ? 400 : $code,
                ['Content-Type' => 'application/json'],
                json_encode([
                    'msg' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => explode("\n", $e->getTraceAsString())
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            );
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

        $container = container();
        if (!class_exists($controller_name)) {
            throw new RuntimeException("{$route} - Controller not found: {$controller_name}", 404);
        }

        if (!$container->has($controller_name)) {
            $container->set($controller_name, $controller_name);
        }
        $controller = $container->get($controller_name);

        if (!method_exists($controller, $action_name)) {
            throw new RuntimeException("{$route} - Action not found: {$action_name}", 404);
        }
        return [$controller, $action_name];
    }

    private static function timeout(Output $output): PromiseInterface
    {
        $timeout = config()->get('web.timeout', 30);
        $timer = loop()->addTimer($timeout, function () use ($output) {
            $output->setData(Output::TYPE_JSON, ['msg' => 'The connection is timeout']);
            $output->setStatusCode(400);
            $output->send();
        });
        return $output->promise()->then(function ($response) use ($timer) {
            loop()->cancelTimer($timer);
            return $response;
        });
    }
}

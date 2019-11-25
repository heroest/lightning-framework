<?php
namespace Lightning\MVC;

use function Lightning\container;
use function React\Promise\race;
use Lightning\MVC\ResponseBuilder;
use React\Promise\{Deferred, PromiseInterface};
use React\Http\{Server, Response};
use React\Socket\Server as SocketServer;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Throwable;


class Application extends \Lightning\Base\Application
{
    private $namespace;

    public function __construct($namespace)
    {
        $this->namespace = $namespace;
        $this->bootstrap();
    }

    public function run(int $port = 80)
    {
        $loop = container()->get('loop');
        $server = new Server([$this, 'handleRequest']);
        $server->listen(new SocketServer($port, $loop));
        $loop->run();
    }

    public function handleRequest(ServerRequestInterface $request)
    {
        try {
            $callable = $this->fetchUrl($request->getUri()->getPath());
            $builder = new ResponseBuilder();
            call_user_func_array($callable, [$request, $builder]);
            $promise = $builder->promise();
            return self::timeout($promise);
        } catch (Throwable $e) {
            $code = $e->getCode();
            return new Response(
                empty($code) ? 400 : $code,
                ['Content-Type' => 'application/json'], 
                json_encode([
                    'msg' => $e->getMessage(), 
                    'file' => $e->file(), 
                    'line' => $e->getLine()
                ], JSON_UNESCAPED_UNICODE)
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
        list($controller_name, $action_name) = self::fetchControllerAction($this->namespace, $route);

        $container = container();
        if (!class_exists($controller_name)) {
            throw new RuntimeException("Page not found: {$route}", 404);
        }
        
        if (!$container->has($controller_name)) {
            $container->set($controller_name, $controller_name);
        }
        $controller = $container->get($controller_name);

        if (!method_exists($controller, $action_name)) {
            throw new RuntimeException("Page not found: {$route}", 404);
        }
        return [$controller, $action_name];
    }

    private static function timeout(PromiseInterface $promise): PromiseInterface
    {
        $config = container()->get('config');
        $timeout = $config->get('web.timeout', 30);
        $deferred = new Deferred();
        $timer = container()->get('loop')->addTimer($timeout, function() use ($deferred) {
            $deferred->resolve(
                new Response(
                    400, 
                    ['Content-Type' => 'application/json'], 
                    json_encode(['msg' => "The conneciton is timeout"])
                ));
        });
        $promise->then(function() use ($timer) {
            container()->get('loop')->cancelTimer($timer);
        });
        return race([$promise, $deferred->promise()]);
    }
}
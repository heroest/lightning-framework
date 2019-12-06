<?php
namespace Lightning\Base;

abstract class Application
{
    /**
     * 初始化系统组件以及 一些常用的组件
     *
     * @return void
     */
    protected function bootstrap()
    {
        $container = \Lightning\container();
        $container->set('app', $this, true);
        $container->set('loop', \Lightning\System\LoopFactory::buildLoop(), true);
        $container->set('event-manager', \Lightning\Event\EventManager::class, true);
        
        $container->set('config', function() {
            $config = new \Lightning\System\Config();
            $config->loadFromDirectory(BASE_PATH . '/app/Config');
            return $config;
        });
        $container->set('dbm', function() {
            $pool = new \Lightning\Database\Pool();
            return new \Lightning\Database\DBManager($pool);
        });
        $container->set('http-client', \Lightning\Http\HttpClient::class);
    }

    abstract public function run();

    protected static function fetchControllerAction(string $namespace, string $route)
    {
        $last = strrpos($route, '/');
        $action_name = substr($route, $last + 1);
        $controller_name = substr($route, 0, (strlen($route) - strlen($action_name) - 1));
        $controller_name = self::formatControllerName($namespace, $controller_name);
        $action_name = self::formatActionName($action_name);
        return [$controller_name, $action_name];
    }

    protected static function formatControllerName(string $namespace, string $name): string
    {
        $name = self::normalize($name);
        $name = ucfirst(str_replace('/', '\\', $name));
        return "{$namespace}\\Controller\\{$name}Controller";
    }

    protected static function formatActionName(string $name): string
    {
        $name = self::normalize($name);
        return "{$name}Action";
    }

    protected static function normalize(string $str): string
    {
        $result = [];
        $next_upper = false;
        foreach (str_split($str) as $chr) {
            if ($next_upper == true) {
                $chr = strtoupper($chr);
                $next_upper = false;
            } elseif ($chr == '-') {
                $next_upper = true;
                continue;
            }
            $result[] = $chr;
        }
        return implode('', $result);
    }
}
<?php
namespace Lightning\Base;

abstract class Application
{
    protected static $namespace;

    public function setApp(string $namespace, string $path)
    {
        self::$namespace = $namespace;

        $container = \Lightning\container();
        $container->setClassMap($namespace, $path);
        $container->registerAutoload();

        $config = $container->get('config');
        $config->loadFromDirectory($path . '/Config');
    }

    /**
     * 初始化系统组件以及 一些常用的组件
     *
     * @return void
     */
    protected function bootstrap()
    {
        $container = \Lightning\container();
        $container->set('app', $this, true);
        $container->set('config', \Lightning\System\Config::class, true);
        $container->set('http-client', function () {
            return \Lightning\Http\Client::getInstance();
        }, true);
        
        //db-config
        $container->set('query-manager', function () {
            $config = \Lightning\config()->get('database');
            $connection_pool = new \Lightning\Database\ConnectionPool($config);
            /** @var \Lightning\Database\QUeryManager $manager */
            $manager = \Lightning\Database\QueryManager::getInstance();
            $manager->setConnectionPool($connection_pool);
            return $manager;
        }, true);
    }

    abstract public function run();

    protected static function fetchControllerAction(string $route)
    {
        $last = strrpos($route, '/');
        $action_name = substr($route, $last + 1);
        $controller_name = substr($route, 0, (strlen($route) - strlen($action_name) - 1));
        $controller_name = self::formatControllerName($controller_name);
        $action_name = self::formatActionName($action_name);
        return [$controller_name, $action_name];
    }

    protected static function formatControllerName(string $name): string
    {
        $name = ucfirst(str_replace('/', '\\', self::normalize($name)));
        $namespace = self::$namespace;
        return "{$namespace}Controller\\{$name}Controller";
    }

    protected static function formatActionName(string $name): string
    {
        $name = self::normalize($name);
        return "{$name}Action";
    }

    protected static function normalize(string $str): string
    {
        $result = [];
        $dividers = ['-', '\\'];
        $next_upper = false;
        foreach (str_split($str) as $chr) {
            if ($next_upper == true) {
                $chr = strtoupper($chr);
                $next_upper = false;
            } elseif (in_array($chr, $dividers)) {
                $next_upper = true;
                continue;
            }
            $result[] = $chr;
        }
        return implode('', $result);
    }
}
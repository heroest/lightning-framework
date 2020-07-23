<?php
namespace Lightning\Base;

abstract class AbstractSingleton
{
    private static $_instanceStorage = [];

    abstract protected function __construct();

    /**
     * 返回单例实例
     *
     * @return Object
     */
    final public static function getInstance()
    {
        $called_class = get_called_class();
        if (!isset(self::$_instanceStorage[$called_class])) {
            self::$_instanceStorage[$called_class] = new $called_class();
        }
        return self::$_instanceStorage[$called_class];
    }
}
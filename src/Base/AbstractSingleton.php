<?php
namespace Lightning\Base;

abstract class AbstractSingleton
{
    private static $objectStorage = [];

    abstract protected function __construct();

    /**
     * 返回单例实例
     *
     * @return Object
     */
    final public static function getInstance()
    {
        $class_name = get_called_class();
        if (!isset(self::$objectStorage[$class_name])) {
            self::$objectStorage[$class_name] = new $class_name();
        }
        return self::$objectStorage[$class_name];
    }
}
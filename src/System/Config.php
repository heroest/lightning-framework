<?php
namespace Lightning\System;

use function Lightning\{isAssoc, arrayMergeRecursive};
use Lightning\Exceptions\SystemException;
use InvalidArgumentException;
use DirectoryIterator;

class Config
{
    const FILE_JSON = 'json';
    const FILE_XML = 'xml';
    const FILE_PHP = 'php';

    private $storage = [];

    public function __construct()
    {

    }

    public function get(string $key, $default = null, bool $deep = true)
    {
        if ($deep === false) {
            return isset($this->storage[$key]) ? $this->storage[$key] : $default;
        } else {
            $item = self::deepGet($key, $this->storage);
            return null === $item ? $default : $item;
        }
    }

    public function loadFromDirectory(string $path)
    {
        $path = rtrim($path, '/\\');
        foreach (new DirectoryIterator($path) as $file) {
            if ($file->isDot()) {
                continue;
            }

            $file_path = $path . '/' . $file->getFilename();
            if ($file->isFile()) {
                $this->loadFromFile($file_path);
            } else {
                $this->loadFromDirectory($file_path);
            }
        }
    }

    /**
     * load config from file
     *
     * @param string $file_path
     * @return void
     * @throws SystemException
     */
    public function loadFromFile(string $file_path, string $file_type = '')
    {
        if (!file_exists($file_path)) {
            throw new SystemException("File does not exist in: {$file_path}");
        }

        if (empty($file_type)) {
            $ext = pathinfo($file_path, PATHINFO_EXTENSION);
            $file_type = strtolower($ext);
        }

        switch ($file_type) {
            case 'json': 
                $this->loadJson(file_get_contents($file_path));
                break;

            case 'xml':
                $this->loadXml(file_get_contents($file_path));
                break;

            case 'php':
                $data = include_once $file_path;
                $this->load($data);
                break;

            default:
                throw new InvalidArgumentException("Unknown file type: {$file_type}");
        }
        
    }

    /**
     * load config from json string
     *
     * @param string $json
     * @return void
     * @throws SystemException
     */
    public function loadJson(string $json)
    {
        $data = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new SystemException("Failed to decode json file: " . json_last_error_msg());
        }

        $this->load($data);
    }

    /**
     * laod config from xml string
     *
     * @param string $xml
     * @return void
     * @throws SystemException
     */
    public function loadXml(string $xml)
    {
        if (false === $object = simplexml_load_string($xml)) {
            throw new SystemException("Failed to decode xml file");
        }

        $data = json_decode(json_encode($object), true);
        $this->load($data);
    }

    /**
     * load config from php array
     *
     * @param array $data
     * @return void
     */
    public function load(array $data)
    {
        if (!isAssoc($data)) {
            throw new InvalidArgumentException("Expected an associative array");
        }
        $this->storage = arrayMergeRecursive(
            $this->storage,
            $data
        );
    }

    /**
     * get config in deeper level
     *
     * @param string $key
     * @param array $storage
     * @return void
     */
    private static function deepGet(string $key, array $storage)
    {
        if (false === $index = strpos($key, '.')) {
            return isset($storage[$key]) ? $storage[$key] : null;
        } else {
            $current = substr($key, 0, $index);
            $next = substr($key, $index + 1);
            if (empty($storage[$current])) {
                return null;
            } else {
                return self::deepGet($next, $storage[$current]);
            }
        }
    }
}
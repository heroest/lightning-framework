<?php
namespace Lightning\Builder;

use Iterator;
use ArrayIterator;
use DirectoryIterator;
use InvalidArgumentException;
use function Lightning\uxPath;

class FileCollection
{
    private $basePath = '';
    private $storage = [];
    private $ignores = [];

    public function __construct(string $base_path)
    {
        $this->basePath = $base_path;
    }

    public function ignore($pattern)
    {
        if (is_string($pattern)) {
            $this->ignores[] = $pattern;
        } elseif (is_array($pattern)) {
            $this->ignores = array_merge($this->ignores, $pattern);
        } else {
            throw new InvalidArgumentException("Parameter expects to be string or array");
        }
    }

    public function scan(?string $path = null)
    {
        if (empty($path)) {
            $path = $this->basePath;
        }

        if (empty($this->ignores)) {
            $pattern = null;
        } else {
            $pattern = '#(' . implode('|', $this->ignores) . ')#';
        }

        foreach (new DirectoryIterator($path) as $sub) {
            if ($sub->isDot()) {
                continue;
            }

            $full = uxPath($path . '/' . $sub->getFilename());
            if (self::shouldIgnore($full, $pattern)) {
                continue;
            } elseif ($sub->isFile()) {
                $this->storage[$full] = $full;
            } else {
                $this->scan($full);
            }
        }
    }

    public function getIterator(): Iterator
    {
        return new ArrayIterator($this->storage);
    }

    private static function shouldIgnore(string $path, ?string $pattern): bool
    {
        if ($pattern === null) {
            return false;
        } elseif (preg_match($pattern, $path)) {
            return true;
        } else {
            return false;
        }
    }

}
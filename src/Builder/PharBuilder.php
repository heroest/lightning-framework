<?php
namespace Lightning\Builder;

use Iterator;
use Phar;
use function Lightning\uxPath;

class PharBuilder
{
    private $iterator;

    public function __construct(Iterator $iterator)
    {
        $this->iterator = $iterator;
    }

    public function create(string $file_name, string $stub)
    {
        $file_name = uxPath($file_name);
        $stub = uxPath($stub);

        if (file_exists($file_name)) {
            unlink($file_name);
        }
        
        $phar = new Phar($file_name);
        $phar->buildFromIterator($this->iterator);
        $phar->setStub($phar->createDefaultStub($stub));
    }
}
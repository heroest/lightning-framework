<?php

namespace Lightning\Coroutine;

use Throwable;
use SplObjectStorage;
use SplStack;
use Generator;
use React\Promise\PromiseInterface;
use Lightning\Base\AbstractSingleton;
use Lightning\Coroutine\SystemCall\InterfaceSystemCall;
use Lightning\Coroutine\{Coroutine, CoroutineException};
use Symfony\Component\OptionsResolver\OptionsResolver;
use function Lightning\{config, setInterval};

class CoroutineScheduler extends AbstractSingleton
{
    private $coroutineStack;
    private $coroutineWorking;
    private $maxCoroutineTimeIdle = 30;
    private $maxCoroutinePoolSize = 16;

    protected function __construct()
    {
        $this->coroutineStack = new SplStack();
        $this->coroutineWorking = new SplObjectStorage();
        $this->loadConfig();
    }

    public function execute(callable $callback, array $params = []): ?Coroutine
    {
        $result = call_user_func_array($callback, $params);
        if ($result instanceof Generator) {
            $coroutine = $this->createCoroutine();
            $coroutine->initialize($result);
            $this->coroutineWorking->attach($coroutine);
            $this->registerCoroutineDestroyer();
            return $coroutine;
        } else {
            return null;
        }        
    }

    public function cancelCoroutine(Coroutine $coroutine)
    {
        //父协程进行通知
        $parent = $coroutine->parent();
        //回收当前协程再通知父类
        $this->recycleCoroutine($coroutine);
        if (null !== $parent) {
            $this->handleYielded($parent, new CoroutineException('Child coroutine has been cancelled'));
        }
    }

    public function tick()
    {
        if (0 === $count = $this->coroutineWorking->count()) {
            return;
        }

        $this->coroutineWorking->rewind();
        while ($count--) {
            /** @var \Lightning\Coroutine\Coroutine $coroutine */
            if (null === $coroutine = $this->coroutineWorking->current()) {
                return;
            }
            $this->coroutineWorking->next();

            if ($coroutine->isOverTime()) {
                $coroutine->cancel('coroutine-timeout');
                $this->recycleCoroutine($coroutine);
            } elseif ($coroutine->inState(Coroutine::STATE_PROGRESS)) {
                continue;
            } elseif ($coroutine->inState(Coroutine::STATE_IDLE)) {
                $this->recycleCoroutine($coroutine);
            } else {
                $yielded = $coroutine->get();
                $this->handleYielded($coroutine, $yielded);
            }
        }
    }

    public function isEmpty()
    {
        return 0 === $this->coroutineWorking->count();
    }

    public function getCount()
    {
        return $this->coroutineWorking->count();
    }
    
    private function handleYielded(Coroutine $coroutine, $yielded)
    {
        if ($coroutine->valid()) {
            //coroutine is not finished yet
            if ($yielded instanceof PromiseInterface) {
                $this->handlePromise($coroutine, $yielded);
            } elseif ($yielded instanceof Generator) {
                $this->handleGenerator($coroutine, $yielded);
            } elseif ($yielded instanceof InterfaceSystemCall) {
                $this->handleYielded($coroutine, $yielded->execute($coroutine));
            } else {
                $this->coroutineTick($coroutine, $yielded);
            }
        } else { 
            //coroutine is finished
            $parent = $coroutine->parent();
            $this->recycleCoroutine($coroutine);
            if (null !== $parent) { //pass it to parent
                $this->coroutineTick($parent, $yielded);
            } elseif ($yielded instanceof Throwable) {
                echo "coroutine-uncaught-exception: " . $yielded;
            } else {
                //do nothing since no-one interested
            }
        }
        return;
    }

    private function coroutineTick(Coroutine $coroutine, $result)
    {
        try {
            $coroutine->set($result);
        } catch (Throwable $e) {
            $parent = $coroutine->parent();
            $this->recycleCoroutine($coroutine);
            if (null !== $parent) { //if coroutine did not catch throwable, pass it to parent
                $this->coroutineTick($parent, $e);
            } else { //if there's no parent, throw it now
                //uncaught exception
            }
        }
    }

    private function handlePromise(Coroutine $coroutine, PromiseInterface $promise)
    {
        $coroutine->appendProgress($promise);
        $callback = function ($value) use ($coroutine) {
            $this->coroutineTick($coroutine, $value);
        };
        $promise->then($callback, $callback);
    }

    private function handleGenerator(Coroutine $parent, Generator $generator)
    {
        $child = $this->createCoroutine();
        $child->initialize($generator)
            ->setParent($parent);
        $this->coroutineWorking->attach($child);
        
        $parent->setChild($child)
            ->appendProgress($child->promise()); //父协程挂起直到子协程fullfilled
        
    }

    private function createCoroutine(): Coroutine
    {
        return ($this->coroutineStack->count() > 0)
                ? $this->coroutineStack->pop()
                : new Coroutine();
    }

    private function recycleCoroutine(Coroutine $coroutine)
    {
        //子协程直接回收
        if (null !== $child = $coroutine->child()) {
            $this->recycleCoroutine($child);
        }

        if ($coroutine->valid()) {
            $coroutine->cancel('coroutine-recycle');
        }
        
        $coroutine->reset();
        $this->coroutineWorking->detach($coroutine);
        $this->coroutineStack->push($coroutine);
    }

    private function registerCoroutineDestroyer()
    {
        static $callable = null;
        if (null !== $callable) {
            return;
        }

        $callable = function () {
            $removed = true;
            while ($removed) {
                $removed = false;
                $count = $this->coroutineStack->count();
                if ($count <= $this->maxCoroutinePoolSize) {
                    return;
                } else {
                    /** @var Coroutine $coroutine */
                    $coroutine = $this->coroutineStack->bottom();
                    if ($coroutine->inState(Coroutine::STATE_IDLE) and $coroutine->getStateDuration() > $this->maxCoroutineTimeIdle) {
                        $this->coroutineStack->shift();
                        $removed = true;
                    }
                }
            }
        };
        setInterval($callable, 30);
    }

    private function loadConfig()
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults([
            'max_time_idle' => 30,
            'max_pool_size' => 16
        ])
        ->setAllowedTypes('max_time_idle', ['float', 'int'])
        ->setAllowedTypes('max_pool_size', ['int']);

        $options = $resolver->resolve(config()->get('coroutine'));
        $this->maxCoroutinePoolSize = $options['max_pool_size'];
        $this->maxCoroutineTimeIdle = $options['max_time_idle'];
    }
}

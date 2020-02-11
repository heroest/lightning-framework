<?php

namespace Lightning\Database;

use Generator;
use Symfony\Component\OptionsResolver\OptionsResolver;
use React\Promise\{Deferred, PromiseInterface};
use function React\Promise\resolve;
use Lightning\Database\Connection;
use Lightning\Exceptions\DatabaseException;
use function Lightning\{container, await, config};


class Pool
{
    const CONNECTION_REFRESH_SECONDS = 300;

    private $master = [];
    private $masterBench = [];
    private $slave = [];
    private $slaveBench = [];

    private $waitingList = [];
    private $waitingListCount = 0;
    private $waitingListBlock = null;

    public function __construct()
    {
        if (!extension_loaded('mysqli')) {
            throw new DatabaseException("module mysqli is required");
        }

        $this->bootstrap();
    }

    public function getConnection(string $connection_name, string $role): PromiseInterface
    {
        $role = strtolower($role);
        if (!in_array($role, ['master', 'slave'])) {
            throw new DatabaseException("Undefined role: {$role}");
        }

        if (empty($this->$role[$connection_name])) {
            throw new DatabaseException("Unable to get [{$role}] conneciton from [{$connection_name}]");
        }

        if ($connection = $this->doGetConnection($connection_name, $role)) {
            return resolve($connection);
        } else {
            $max_limit = config()->get('database.connection_waiting_list_size', 200);
            if ($this->waitingListCount > $max_limit) {
                $this->waitingListBlockWait();
            }
            $deferred = new Deferred();
            $key = "{$connection_name}:{$role}";
            if (!isset($this->waitingList[$key])) {
                $this->waitingList[$key] = [];
            }
            $this->waitingList[$key][] = $deferred;
            $this->waitingListCount++;
            return $deferred->promise();
        }
    }

    private function bootstrap()
    {
        foreach (config()->get('database.connections') as $name => $conf) {
            $this->initialize($name, $conf);
        }
        $this->registerWaitingListResolver();
        $this->registerConnectionWatcher();
    }

    private function doGetConnection($connection_name, $role)
    {
        foreach (self::randomIterator($this->$role[$connection_name]) as $connection) {
            if ($connection->getState() === Connection::STATE_IDLE) {
                return $connection;
            } elseif (($connection->getState() === Connection::STATE_CLOSE) and $connection->open()) {
                return $connection;
            }
        }

        $bench = "{$role}Bench";
        if (!isset($this->$bench[$connection_name])) {
            return null;
        }
        $backup = null;
        foreach (self::randomIterator($this->$bench[$connection_name]) as $connection) {
            if ($connection->getState() === Connection::STATE_IDLE) {
                return $connection;
            } elseif (($connection->getState() === Connection::STATE_CLOSE)) {
                //pick a closed connection if no other idle connection
                $backup = $connection;
            }
        }
        if ((null !== $backup) and $backup->open()) {
            return $backup;
        }
        return null;
    }

    private function waitingListBlockWait()
    {
        if ($this->waitingListBlock === null) {
            $this->waitingListBlock = new Deferred();
        }
        await($this->waitingListBlock->promise());
        $this->waitingListBlock = null;
    }

    private function registerWaitingListResolver()
    {
        $event_name = Connection::eventName(Connection::ACTION_STATE_CHANGED, Connection::STATE_IDLE);
        container()->get('event-manager')->on($event_name, function ($event) {
            if (empty($this->waitingList)) {
                return;
            }

            $data = $event->data;
            $key = "{$data['connection_name']}:{$data['role']}";
            if (isset($this->waitingList[$key])) {
                $deferred = array_shift($this->waitingList[$key]);
                if (empty($this->waitingList[$key])) {
                    unset($this->waitingList[$key]);
                }
                $this->waitingListCount--;
                $deferred->resolve($data['connection']);
            }

            //resolve block-wait
            if ($this->waitingListBlock === null) {
                return;
            }
            $max_limit = config()->get('database.connection_waiting_list_size', 200);
            if ($this->waitingListCount < $max_limit) {
                $this->waitingListBlock->resolve(true);
            }
        });
    }

    private function registerConnectionWatcher()
    {
        container()
            ->get('loop')
            ->addPeriodicTimer(30, function ($timer) {
                $live_count = 0;
                foreach (['master', 'slave'] as $role) {
                    //keep connection alive
                    foreach ($this->$role as $name => $list) {
                        foreach ($list as $connection) {
                            if (($connection->getState() === Connection::STATE_IDLE) and ($connection->ping() == false)) {
                                $connection->open();
                            }

                            if ($connection->getState() >= Connection::STATE_IDLE) {
                                $live_count++;
                            }
                        }
                    }

                    //stop connection if being idle for more than 30 sec
                    $close_count = 0;
                    $bench = "{$role}Bench";
                    foreach ($this->$bench as $name => $list) {
                        foreach ($list as $connection) {
                            if (($connection->getState() === Connection::STATE_IDLE) and ($connection->getStateDuration() >= 30)) {
                                $connection->close();
                                $close_count++;
                            }
                        }
                    }
                }
                echo "live: {$live_count}, closed: {$close_count}\r\n";
            });
    }

    private function initialize($name, $conf)
    {
        $this->master[$name] = [];
        $this->slave[$name] = [];
        $this->masterBench[$name] = [];
        $this->slaveBench[$name] = [];
        $resolver = $this->getOptionResolver();

        foreach ($conf as $row) {
            $option = $resolver->resolve($row);
            $role = $option['role'];
            $bench = "{$role}Bench";
            foreach (range(1, $option['num_connection']) as $index) {
                $this->$role[$name][] = new Connection($name, $option);
            }

            $num_bench = $option['max_num_connection'] - $option['num_connection'];
            if ($num_bench > 0) {
                foreach (range(1, $num_bench) as $index) {
                    $this->$bench[$name][] = new Connection($name, $option);
                }
            }
        }
    }

    private function getOptionResolver(): OptionsResolver
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults([
            'port' => 3306,
            'role' => 'master',
            'max_num_connection' => 0
        ]);
        $resolver->setRequired(['host', 'username', 'password', 'dbname', 'num_connection']);
        $resolver->setAllowedValues('role', ['master', 'slave']);
        $resolver->setAllowedTypes('num_connection', 'int');
        $resolver->setAllowedTypes('max_num_connection', 'int');
        return $resolver;
    }

    private static function randomIterator(array $list): Generator
    {
        $index_map = range(0, count($list) - 1);
        shuffle($index_map);
        foreach ($index_map as $index) {
            yield $list[$index];
        }
    }
}

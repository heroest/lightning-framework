<?php

namespace Lightning\Database;

use Symfony\Component\OptionsResolver\OptionsResolver;
use React\Promise\{Deferred, PromiseInterface};
use Lightning\Database\{DatabaseException, Connection, Transaction};
use Lightning\Base\AbstractSingleton;
use function React\Promise\{resolve, reject};
use function Lightning\{setInterval, getObjectId};

class ConnectionPool extends AbstractSingleton
{
    private $master = [];
    private $masterBackup = [];
    private $slave = [];
    private $slaveBackup = [];

    private $pendingConnectionList = [];
    private $transactionPendingConnectionList = [];
    private $pendingConnectionListCount = 0;

    /**
     * 预处理连接上限
     *
     * @var integer
     */
    private $maxNumPendingQuery = 200;

    /**
     * 预处理连接流量阻塞锁
     *
     * @var array
     */
    private $pendingConnectionThresholdLocks = [];

    protected function __construct()
    {
        if (!extension_loaded('mysqli')) {
            throw new DatabaseException("module mysqli is required");
        }
    }
    
    public function bootstrap(array $config)
    {
        $config = $this->resolveOptions($config);
        foreach ($config['connections'] as $name => $option) {
            $this->initializeConnections($name, $option);
        }
        $this->registerPendingConnectionListResolver();
        $this->registerConnectionMonitor();
    }

    /**
     * 获取待链接限制阻塞锁
     *
     * @return PromiseInterface
     */
    public function getPendingConnectionThresholdLock(): PromiseInterface
    {
        if ($this->pendingConnectionListCount < $this->maxNumPendingQuery) {
            return resolve(true);
        } else {
            $deferred = new Deferred(null);
            $this->pendingConnectionThresholdLocks[] = $deferred;
            return $deferred->promise();
        }
    }

    /**
     * 获取数据库连接
     *
     * @param string $name
     * @param string $role
     * @param Transaction $transaction
     * @return PromiseInterface
     */
    public function getConnection(string $name, string $role, Transaction $transaction = null): PromiseInterface
    {
        if (null !== $transaction and $transaction->isClosed()) {
            reject(new DatabaseException("transaction is closed already"));
        } elseif (null !== $connection = $this->doGetConnection($name, $role, $transaction)) {
            return resolve($connection);
        }

        $deferred = null;
        if ($transaction === null) {
            $key = "{$name}:{$role}";
            $canceller = function () use ($key, &$deferred) {
                foreach ($this->pendingConnectionList[$key] as $index => $pending) {
                    if ($deferred === $pending) {
                        unset($this->pendingConnectionList[$key][$index]);
                        break;
                    }
                }
                throw new DatabaseException("pending-connection is cancelled due to promise-cancelling");
            };
            $deferred = new Deferred($canceller);
            $this->pendingConnectionList[$key][] = $deferred;
        } else {
            $deferred = null;
            $transaction_id = getObjectId($transaction);
            $canceller = function () use ($transaction_id, &$deferred) {
                foreach ($this->transactionPendingConnectionList[$transaction_id] as $index => $pending) {
                    if ($pending === $deferred) {
                        unset($this->transactionPendingConnectionList[$transaction_id][$index]);
                    }
                }
                throw new DatabaseException("pending-connection is cancelled due to promise-cancelling");
            };
            $deferred = new Deferred($canceller);
            $this->transactionPendingConnectionList[$transaction_id][] = ['deferred' => $deferred, 'transaction' => $transaction];
        }
        $this->pendingConnectionListCount++;
        return $deferred->promise();        
    }

    private function registerConnectionMonitor()
    {
        $callback = function () {
            foreach (['master', 'slave'] as $role) {
                foreach ($this->$role as $list) {
                    /** @var Connection $connection */
                    foreach ($list as $connection) {
                        if ($connection->inState(Connection::STATE_IDLE)) {
                            if ($connection->getConnectedDuration() > 1800) { //连接上超过1800秒，关闭下链接
                                $connection->close();
                            } elseif ($connection->getStateDuration() > 60) { //闲置超过60秒，ping一下检查连接
                                $connection->ping() ?: $connection->close();
                            }
                        }
                    }
                }
    
                $backup = "{$role}Backup";
                foreach ($this->$backup as $list) {
                    /** @var Connection $connection */
                    foreach ($list as $connection) {
                        if ($connection->stayOver(Connection::STATE_IDLE, 30)) { //动态池里的连接闲置超过30秒就关闭
                            $connection->close();
                        } elseif ($connection->inState(Connection::STATE_IDLE) and ($connection->getConnectedDuration() > 1800)) {
                            $connection->close();
                        }
                    }
                }
            }
        };
        setInterval($callback, 30);
    }

    /**
     * 注册待连接请求的处理者
     *
     * @return void
     */
    private function registerPendingConnectionListResolver()
    {
        $callable = function () {
            if (0 === $this->pendingConnectionListCount) {
                $this->removePendingConnectionThresholdLock();
                return;
            }
            //处理非事务等待连接
            foreach ($this->pendingConnectionList as $key => $pending_list) {
                list($name, $role) = explode(':', $key);
                foreach ($pending_list as $index => $pending) {
                    if (null === $connection = $this->doGetConnection($name, $role)) {
                        break; //说明resolve不了， 直接break处理下一个connectionName,role的连接
                    }
                    unset($this->pendingConnectionList[$key][$index]);
                    $this->pendingConnectionListCount--;
                    $pending->resolve($connection);
                }
            }

            //处理事务等待连接
            foreach ($this->transactionPendingConnectionList as $transaction_id => $list) {
                foreach ($list as $index => $pending) {
                    $transaction = $pending['transaction'];
                    $deferred = $pending['deferred'];
                    $name = $transaction->getConnectionName();
                    if ($transaction->isClosed()) {
                        unset($this->transactionPendingConnectionList[$transaction_id][$index]);
                        $this->pendingConnectionListCount--;
                        $deferred->reject(new DatabaseException("transaction is closed already"));
                    } elseif (null === $connection = $this->doGetConnection($name, 'master', $transaction)) {
                        break;
                    } else {
                        unset($this->transactionPendingConnectionList[$transaction_id][$index]);
                        $this->pendingConnectionListCount--;
                        $deferred->resolve($connection);
                    }
                }
            }

            $this->removePendingConnectionThresholdLock();
        };
        setInterval($callable, 0);
    }
    
    /**
     * 释放待链接阻塞锁
     *
     * @return void
     */
    private function removePendingConnectionThresholdLock()
    {
        if (0 === count($this->pendingConnectionThresholdLocks)) {//没有发现阻塞锁
            return;
        }

        if ($this->pendingConnectionListCount < $this->maxNumPendingQuery) {
            $first_lock = array_shift($this->pendingConnectionThresholdLocks);
            $first_lock->resolve(true);
        }
    }

    /**
     * 尝试获取一条数据库链接
     *
     * @param string $name
     * @param string $role
     * @param Transaction $transaction
     * @return Connection|null
     */
    private function doGetConnection(string $name, string $role, Transaction $transaction = null): ?Connection
    {
        $role = $this->fallbackToMaster($name, $role);
        foreach ([$role, "{$role}Backup"] as $group) {
            /** @var Connection $connection */
            foreach (self::randomIterator($this->$group[$name]) as $connection) {
                $candicate = null;
                if ($connection->inState(Connection::STATE_IDLE)) {
                    $candicate = $connection;
                } elseif ($connection->inState(Connection::STATE_CLOSE) and $connection->open()) {
                    $candicate = $connection;
                }

                if (null === $candicate) {
                    continue;
                } elseif (null !== $transaction) {
                    if ($candicate->inTransaction() and $transaction === $candicate->getTransaction()) {
                        return $candicate;
                    } else {
                        continue;
                    }
                } elseif (false === $candicate->inTransaction()) {
                    return $candicate;
                }
            }
        }
        return null;
    }

    /**
     * 是否需要fallbackToMaster
     *
     * @param string $name
     * @param string $role
     * @return string
     */
    private function fallbackToMaster(string $name, string $role)
    {
        return isset($this->$role[$name]) ? $role : 'master';
    }

    /**
     * 初始化数据库连接
     *
     * @param string $name
     * @param array $options
     * @return void
     */
    private function initializeConnections($name, $options)
    {
        $this->master[$name] 
            = $this->slave[$name] 
            = $this->masterBackup[$name] 
            = $this->slaveBackup[$name] 
            = [];
        foreach ($options as $option) {
            $role = $option['role'];
            foreach (range(1, $option['num_connections']) as $i) {
                $this->$role[$name][] = new Connection($name, $role, $option);
            }

            $num_backup = $option['max_num_connections'] - $option['num_connections'];
            if ($num_backup > 0) {
                $backup = "{$role}Backup";
                foreach (range(1, $num_backup) as $i) {
                    $this->$backup[$name][] = new Connection($name, $role, $option);
                }
            }
        }
    }

    /**
     * 封装array为随机排序数组
     *
     * @param array $list
     * @return array
     */
    private static function randomIterator(array $list): array
    {
        shuffle($list);
        return $list;
    }

    private function resolveOptions(array $options)
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults([
            'max_num_pending_query' => 200
        ]);
        $resolver->setAllowedTypes('connections', 'array');
        $resolver->setAllowedValues('max_num_pending_query', ['int']);
        return $resolver->resolve($options);
    }
}
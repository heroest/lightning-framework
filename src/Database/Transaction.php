<?php

namespace Lightning\Database;

use Throwable;
use SplObjectStorage;
use React\Promise\{Deferred, PromiseInterface};
use Lightning\Exceptions\DatabaseException;
use Lightning\Database\{Connection, Query};
use function Lightning\{container};
use function React\Promise\{resolve, reject};

/**
 * Mysql Transaction
 */
class Transaction
{
    const SQL_ROLLBACK = 'ROLLBACK;';
    const SQL_COMMIT = 'COMMIT;';
    private $connectionName = '';
    /** @var \Lightning\Database\Connection $connection */
    private $connection = null;
    private $pendingConnections = [];
    private $connectionResolver = null;
    private $isClosed = false;

    private function __construct(string $connection_name)
    {
        $this->connectionName = $connection_name;
        $this->registerPendingConnectionResolver();
    }

    public function getConnectionName(): string
    {
        return $this->connectionName;
    }

    public function getConnection(): PromiseInterface
    {
        if (true === $this->isClosed) {
            return reject(new DatabaseException("Transaction has been closed."));
        } elseif ($this->connection === null) {
            $dbm = container()->get('dbm');
            $promise = $dbm->getTransactionConnection($this->connectionName, $this);
            return $promise->then(function (Connection $connection) {
                $this->connection = $connection;
                return $connection;
            }, function ($error) {
                throw $error;
            });
        } elseif (Connection::STATE_TRANSACTION_IDLE === $this->connection->getState()) {
            return resolve($this->connection);
        } else {
            $cannceller = function () {
                throw new DatabaseException("Transaction has been canceled.");
            };
            $deferred = new Deferred($cannceller);
            $this->pendingConnections[] = $deferred;
            return $deferred->promise();
        }
    }

    public static function start(string $connection_name): self
    {
        $instance = new self($connection_name);
        $query = Query::useTransaction($instance);
        $query->execute('START TRANSACTION;');
        return $instance;
    }

    public function commit(): PromiseInterface
    {
        return Query::useTransaction($this)
            ->execute(self::SQL_COMMIT)
            ->then(function () {
                $this->closePendingConnections();
                $this->closeTransaction();
                $this->connection->closeTransaction();
                return true;
            }, function ($error) {
                $this->rollback();
                throw $error;
            });
    }

    public function rollback(): PromiseInterface
    {
        $this->closePendingConnections();
        return Query::useTransaction($this)
            ->execute(self::SQL_ROLLBACK)
            ->then(function () {
                $this->closePendingConnections();
                $this->closeTransaction();
                $this->connection->closeTransaction();
                return true;
            }, function ($error) {
                $this->closePendingConnections();
                $this->closeTransaction();
                $this->connection->terminate(new DatabaseException('Transaction has failed.', 500, $error));
                throw $error;
            });
    }

    private function registerPendingConnectionResolver(): void
    {
        $em = container()->get('event-manager');
        $event_name = Connection::eventName(
            Connection::ACTION_STATE_CHANGED,
            Connection::STATE_TRANSACTION_IDLE
        );
        $this->connectionResolver = function ($event) {
            $connection = $event->data['connection'];
            if ($connection !== $this->connection) {
                return;
            };

            $event->stopPropagation();
            if (null !== $deferred = array_shift($this->pendingConnections)) {
                $deferred->resolve($connection);
            }
        };
        $em->on($event_name, $this->connectionResolver);
    }

    private function closePendingConnections(): void
    {
        if (0 === count($this->pendingConnections)) {
            return;
        }

        while (null !== $deferred = array_shift($this->pendingConnections)) {
            $deferred->promise()->cancel();
        }
    }

    private function closeTransaction(): void
    {
        $this->isClosed = true;
        $em = container()->get('event-manager');
        $event_name = Connection::eventName(
            Connection::ACTION_STATE_CHANGED,
            Connection::STATE_TRANSACTION_IDLE
        );
        $em->off($event_name, $this->connectionResolver);
    }
}

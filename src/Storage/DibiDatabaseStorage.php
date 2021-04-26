<?php

namespace Webwings\Session\Storage;

use Dibi\Connection;
use Dibi\Exception as DibiException;
use Webwings\Session\Exception\StorageException;

class DibiDatabaseStorage implements ISessionStorage
{
    /** @var Connection */
    protected $connection;

    /** @var string */
    protected $tableName;

    /**
     * DibiDatabaseStorage constructor.
     * @param Connection $connection
     * @param string $tableName
     */
    public function __construct(Connection $connection, string $tableName)
    {
        $this->connection = $connection;
        $this->tableName = $tableName;
    }

    /**
     * @param string $lockId
     * @param int $lockTimeout
     * @throws StorageException
     */
    public function lock(string $lockId, int $lockTimeout): void
    {
        try {
            $this->connection->query('SELECT GET_LOCK(?, ?) as `lock`', $lockId, $lockTimeout);
        } catch (DibiException $e) {
            throw StorageException::from($e);
        }
    }

    /**
     * @param string $lockId
     * @throws StorageException
     */
    public function unlock(string $lockId): void
    {
        try {
            $this->connection->query('SELECT RELEASE_LOCK(?)', $lockId);
        } catch (DibiException $e) {
            throw StorageException::from($e);
        }
    }

    /**
     * @param string $sessionId
     * @return array
     */
    public function read(string $sessionId): array
    {
        $data = $this->connection
            ->select('*')
            ->from('%n',$this->tableName)
            ->where('id = ?',$sessionId)
            ->fetch();
        if ($data){
            return (array) $data;
        } else {
            return ['id'=>null,'timestamp'=>null,'data'=>''];
        }
    }

    /**
     * @param string $sessionId
     * @param array $data
     * @throws StorageException
     */
    public function write(string $sessionId, array $data): void
    {

        try {
            $row = $this->connection
                ->select("*")
                ->from('%n',$this->tableName)
                ->where('id = ?', $sessionId)->fetch();

            if ($row) {
                unset($data['id']);
                $this->connection->update($this->tableName,$data)->where("id = ?",$sessionId)->execute();
            } else {
                $this->connection->insert($this->tableName,['id' => $sessionId] + $data)->execute();
            }
        } catch (DibiException $e) {
            throw StorageException::from($e);
        }
    }

    /**
     * @param string $sessionId
     * @throws StorageException
     */
    public function delete(string $sessionId): void
    {
        try {
            $this->connection->delete($this->tableName)->where('id = ?', $sessionId)->execute();
        } catch (DibiException $e) {
            throw StorageException::from($e);
        }
    }

    /**
     * @param int $maxTimestamp
     * @throws StorageException
     */
    public function cleanup(int $maxTimestamp): void
    {
        try {
            $this->connection
                ->delete($this->tableName)
                ->where('timestamp < ?', $maxTimestamp)
                ->execute();
        } catch (DibiException $e) {
            throw StorageException::from($e);
        }
    }

    /**
     * @return int
     * @throws StorageException
     */
    public function getServerId(): int
    {
        try {
            return (int) $this->connection->query('SELECT @@server_id as `server_id`')->fetch()->server_id;
        } catch (DibiException $e) {
            throw StorageException::from($e);
        }
    }
}
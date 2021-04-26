<?php

namespace Spaze\Session\Storage;

use Nette\Database\Context;
use Nette\Database\DriverException;
use Spaze\Session\Exception\StorageException;

class NetteDatabaseStorage implements ISessionStorage
{
    /**
     * @var Context
     */
    protected $context;
    /**
     * @var string
     */
    protected $tableName;

    /**
     * NetteDatabaseStorage constructor.
     * @param Context $context
     * @param string $tableName
     */
    public function __construct(Context $context, string $tableName)
    {
        $this->context = $context;
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
            $this->context->query('SELECT GET_LOCK(?, ?) as `lock`', $lockId, $lockTimeout);
        } catch (DriverException $e) {
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
            $this->context->query('SELECT RELEASE_LOCK(?)', $lockId);
        } catch (DriverException $e) {
            throw StorageException::from($e);
        }
    }

    /**
     * @param string $sessionId
     * @return array
     * @throws StorageException
     */
    public function read(string $sessionId): array
    {
        try {
            return $this->context
                ->table($this->tableName)
                ->get($sessionId)
                ->toArray();
        } catch (DriverException $e) {
            throw StorageException::from($e);
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
            $row = $this->context
                ->table($this->tableName)
                ->get($sessionId);

            if ($row) {
                $row->update($data);
            } else {
                $this->context
                    ->table($this->tableName)
                    ->insert([
                        'id' => $sessionId,
                    ] + $data);
            }
        } catch (DriverException $e) {
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
            $this->context
                ->table($this->tableName)
                ->where('id', $sessionId)
                ->delete();
        } catch (DriverException $e) {
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
            $this->context
                ->table($this->tableName)
                ->where('timestamp < ?', $maxTimestamp)
                ->delete();
        } catch (DriverException $e) {
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
            return (int) $this->context->query('SELECT @@server_id as `server_id`')->fetch()->server_id;
        } catch (DriverException $e) {
            throw StorageException::from($e);
        }
    }
}
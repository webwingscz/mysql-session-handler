<?php

namespace Webwings\Session\Storage;

use Webwings\Session\Exception\StorageException;

interface ISessionStorage
{
    /**
     * @param string $lockId
     * @param int $lockTimeout
     * @throws StorageException
     */
    public function lock(string $lockId, int $lockTimeout): void;

    /**
     * @param string $lockId
     * @throws StorageException
     */
    public function unlock(string $lockId): void;

    /**
     * @param string $sessionId
     * @return array TODO Replace array by class
     * @throws StorageException
     */
    public function read(string $sessionId): array;

    /**
     * @param string $sessionId
     * @param array $data TODO Replace array by class
     * @throws StorageException
     */
    public function write(string $sessionId, array $data): void;

    /**
     * @param string $sessionId
     */
    public function delete(string $sessionId): void;

    /**
     * @param int $maxTimestamp
     * @throws StorageException
     */
    public function cleanup(int $maxTimestamp): void;

    /**
     * @return int
     * @throws StorageException
     */
    public function getServerId(): int;
}
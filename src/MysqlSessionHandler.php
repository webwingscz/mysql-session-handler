<?php
declare(strict_types = 1);

namespace Webwings\Session;

use Nette\SmartObject;
use SessionHandlerInterface;
use Spaze\Encryption\Symmetric\StaticKey as StaticKeyEncryption;
use Webwings\Session\Storage\ISessionStorage;

/**
 * Storing session to database.
 * Inspired by: https://github.com/JedenWeb/SessionStorage/
 *
 * @method void onBeforeDataWrite()
 */
class MysqlSessionHandler implements SessionHandlerInterface
{
	use SmartObject;

	/**
	 * Occurs before the data is written to session.
	 *
	 * @var callable[] function ()
	 */
	public $onBeforeDataWrite = [];

	/** @var integer */
	private $lockTimeout = 5;

	/** @var integer */
	private $unchangedUpdateDelay = 300;

	/** @var string */
	private $lockId;

	/** @var string[] */
	private $idHashes = [];

	/** @var array */
	private $row;

	/** @var string[] */
	private $data = [];

	/** @var mixed[] */
	private $additionalData = [];

	/** @var StaticKeyEncryption */
	private $encryptionService;

    /** @var ISessionStorage */
    private $storage;


	public function __construct(ISessionStorage $storage)
	{
        $this->storage = $storage;
    }


	public function setLockTimeout(int $timeout): void
	{
		$this->lockTimeout = $timeout;
	}


	public function setUnchangedUpdateDelay(int $delay): void
	{
		$this->unchangedUpdateDelay = $delay;
	}


	public function setEncryptionService(StaticKeyEncryption $encryptionService): void
	{
		$this->encryptionService = $encryptionService;
	}


	/**
	 * @param string $key
	 * @param mixed $value
	 */
	public function setAdditionalData(string $key, $value): void
	{
		$this->additionalData[$key] = $value;
	}


	private function hash(string $id, bool $rawOutput = true): string
	{
		if (!isset($this->idHashes[$id])) {
			$this->idHashes[$id] = \hash('sha256', $id, true);
		}
		return ($rawOutput ? $this->idHashes[$id] : \bin2hex($this->idHashes[$id]));
	}


	private function lock(): void
	{
		if ($this->lockId === null) {
			$this->lockId = $this->hash(\session_id(), false);
			$this->storage->lock($this->lockId, $this->lockTimeout);
		}
	}


	private function unlock(): void
	{
		if ($this->lockId === null) {
			return;
		}

		$this->storage->unlock($this->lockId);
		$this->lockId = null;
	}


	/**
	 * @param string $savePath
	 * @param string $name
	 * @return boolean
	 */
	public function open($savePath, $name): bool
	{
		$this->lock();

		return true;
	}


	public function close(): bool
	{
		$this->unlock();

		return true;
	}


	/**
	 * @param string $sessionId
	 * @return boolean
	 */
	public function destroy($sessionId): bool
	{
		$hashedSessionId = $this->hash($sessionId);
		$this->storage->delete($hashedSessionId);
		$this->unlock();

		return true;
	}


	/**
	 * @param string $sessionId
	 * @return string
	 */
	public function read($sessionId): string
	{
		$this->lock();
		$hashedSessionId = $this->hash($sessionId);
		$this->row = $this->storage->read($hashedSessionId);

		if ($this->row) {
			$this->data[$sessionId] = ($this->encryptionService ? $this->encryptionService->decrypt($this->row['data']) : $this->row['data']);

			return $this->data[$sessionId];
		}
		return '';
	}


	/**
	 * @param string $sessionId
	 * @param string $sessionData
	 * @return boolean
	 */
	public function write($sessionId, $sessionData): bool
	{
		$this->lock();
		$hashedSessionId = $this->hash($sessionId);
		$time = \time();

		if (!isset($this->data[$sessionId]) || $this->data[$sessionId] !== $sessionData) {
			if ($this->encryptionService) {
				$sessionData = $this->encryptionService->encrypt($sessionData);
			}

			$this->onBeforeDataWrite();

			$this->storage->write($hashedSessionId, [
			    'timestamp' => $time,
                'data' => $sessionData,
            ] + $this->additionalData);
		} elseif ($this->unchangedUpdateDelay === 0 || $time - $this->row['timestamp'] > $this->unchangedUpdateDelay) {
			// Optimization: When data has not been changed, only update
			// the timestamp after a configured delay, if any.
            // TODO Insert will fail if row does not exist, separate method maybe?
            $this->storage->write($hashedSessionId, [
                'timestamp' => $time,
            ]);
		}

		return true;
	}


	/**
	 * @param integer $maxLifeTime
	 * @return boolean
	 */
	public function gc($maxLifeTime): bool
	{
		$maxTimestamp = \time() - $maxLifeTime;

		// Try to avoid a conflict when running garbage collection simultaneously on two
		// MySQL servers at a very busy site in a master-master replication setup by
		// subtracting one tenth of $maxLifeTime (but at least one day) from $maxTimestamp
		// for each server with reasonably small ID except for the server with ID 1.
		//
		// In a typical master-master replication setup, the server IDs are 1 and 2.
		// There is no subtraction on server 1 and one day (or one tenth of $maxLifeTime)
		// subtraction on server 2.
		$serverId = $this->storage->getServerId();

		if ($serverId > 1 && $serverId < 10) {
			$maxTimestamp -= ($serverId - 1) * \max(86400, $maxLifeTime / 10);
		}

		$this->storage->cleanup($maxTimestamp);

		return true;
	}

}

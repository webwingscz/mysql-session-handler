<?php

namespace Webwings\Session\Exception;

final class StorageException extends \Exception
{
    /**
     * @param \Throwable $e
     * @param string|null $message
     * @return static
     */
    public static function from(\Throwable $e, ?string $message = null): self
    {
        return new static($message ?? $e->getMessage(), 0, $e);
    }
}
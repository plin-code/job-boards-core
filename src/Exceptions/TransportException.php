<?php

declare(strict_types=1);

namespace PlinCode\JobBoards\Exceptions;

use RuntimeException;
use Throwable;

/**
 * The request never produced an HTTP response: DNS failure, refused connection,
 * TLS error, timeout. There is no status code to inspect.
 */
final class TransportException extends RuntimeException implements JobBoardException
{
    public static function requestFailed(string $url, Throwable $previous): self
    {
        return new self(
            sprintf('HTTP request to "%s" failed: %s', $url, $previous->getMessage()),
            previous: $previous,
        );
    }
}

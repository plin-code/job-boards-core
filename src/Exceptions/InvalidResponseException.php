<?php

declare(strict_types=1);

namespace PlinCode\JobBoards\Exceptions;

use RuntimeException;
use Throwable;

/**
 * A response came back, but its body could not be read in the expected shape.
 * Distinct from a non 2xx status, which is not an exception at all.
 */
final class InvalidResponseException extends RuntimeException implements JobBoardException
{
    public static function emptyBody(string $url): self
    {
        return new self(sprintf('Response from "%s" has an empty body.', $url));
    }

    public static function malformedJson(string $url, Throwable $previous): self
    {
        return new self(
            sprintf('Response from "%s" is not valid JSON: %s', $url, $previous->getMessage()),
            previous: $previous,
        );
    }

    public static function unexpectedShape(string $url, string $path, string $actual): self
    {
        return new self(sprintf(
            'Expected an array at "%s" in the response from "%s", got %s.',
            $path,
            $url,
            $actual,
        ));
    }
}

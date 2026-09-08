<?php

declare(strict_types=1);

namespace PlinCode\JobBoards\Http;

use JsonException;
use PlinCode\JobBoards\Exceptions\InvalidResponseException;

/**
 * A response that actually came back, whatever its status. Reading the body is
 * separated from reading the status so a connector can decide for itself
 * whether a 404 is a failure or an answer.
 */
final class Response
{
    private mixed $decoded = null;

    private bool $isDecoded = false;

    public function __construct(
        private readonly string $url,
        private readonly int $status,
        private readonly string $body,
    ) {}

    public function url(): string
    {
        return $this->url;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function successful(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }

    public function failed(): bool
    {
        return ! $this->successful();
    }

    /**
     * The raw body, untouched. XML boards read this.
     */
    public function body(): string
    {
        return $this->body;
    }

    /**
     * The decoded JSON body, or one value out of it addressed with dot notation
     * ("company.name"). Missing keys and an empty body yield $default; a body
     * that is present but is not JSON at all is an error, not a miss.
     *
     * @throws InvalidResponseException
     */
    public function json(?string $key = null, mixed $default = null): mixed
    {
        $decoded = $this->decode();

        if ($key === null) {
            return $decoded;
        }

        $value = $decoded;

        foreach (explode('.', $key) as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * Like json(), but a body that is not JSON at all yields $default instead of
     * throwing. A board serving an HTML maintenance page is a routine event, and
     * a connector wants to log it as a warning rather than route it through a
     * catch-all as an unexpected error.
     */
    public function tryJson(?string $key = null, mixed $default = null): mixed
    {
        try {
            return $this->json($key, $default);
        } catch (InvalidResponseException) {
            return $default;
        }
    }

    /**
     * Read a nested value without an is_array() guard at every level. json()
     * already walks dot notation, but callers analysing at PHPStan level 10 still
     * have to prove the intermediate offsets exist, so every connector ends up
     * writing the same private helper.
     */
    public function nested(string $key, mixed $default = null): mixed
    {
        return $this->tryJson($key, $default);
    }

    /**
     * Same as json(), but insists the value is an array so callers get a typed
     * result instead of writing an is_array() guard around every access.
     *
     * @return array<array-key, mixed>
     *
     * @throws InvalidResponseException
     */
    public function jsonArray(?string $key = null): array
    {
        $value = $this->json($key);

        if (! is_array($value)) {
            throw InvalidResponseException::unexpectedShape(
                $this->url,
                $key ?? '<root>',
                get_debug_type($value),
            );
        }

        return $value;
    }

    /**
     * @throws InvalidResponseException
     */
    private function decode(): mixed
    {
        if ($this->isDecoded) {
            return $this->decoded;
        }

        $body = trim($this->body);

        // A body with nothing in it is an absence, not a malformed document.
        // Boards answering 204, or 200 with no content, decode to null so that
        // json() can honour its $default instead of exploding. jsonArray()
        // still rejects it, because null is not an array.
        if ($body === '') {
            $this->isDecoded = true;

            return $this->decoded = null;
        }

        try {
            $this->decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw InvalidResponseException::malformedJson($this->url, $e);
        }

        $this->isDecoded = true;

        return $this->decoded;
    }
}

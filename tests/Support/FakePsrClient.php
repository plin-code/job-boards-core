<?php

declare(strict_types=1);

namespace PlinCode\JobBoards\Tests\Support;

use GuzzleHttp\Psr7\Response as PsrResponse;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 * A PSR-18 client that answers from a queue and records what it was asked for.
 */
class FakePsrClient implements ClientInterface
{
    /** @var list<RequestInterface> */
    public array $requests = [];

    /** @var list<ResponseInterface> */
    private array $queue = [];

    private bool $throwNetworkError = false;

    /**
     * @param  array<string, string|list<string>>  $headers
     */
    public function respondWith(int $status, string $body = '', array $headers = []): static
    {
        $this->queue[] = new PsrResponse($status, $headers, $body);

        return $this;
    }

    public function throwNetworkError(): static
    {
        $this->throwNetworkError = true;

        return $this;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;

        if ($this->throwNetworkError) {
            throw new FakeNetworkException($request);
        }

        $next = array_shift($this->queue);

        if ($next === null) {
            throw new RuntimeException('No queued response for '.(string) $request->getUri());
        }

        return $next;
    }

    public function lastUri(): string
    {
        $last = $this->requests[count($this->requests) - 1] ?? null;

        if ($last === null) {
            throw new RuntimeException('No request was sent.');
        }

        return (string) $last->getUri();
    }

    /** @return list<string> */
    public function uris(): array
    {
        return array_map(static fn (RequestInterface $r): string => (string) $r->getUri(), $this->requests);
    }
}

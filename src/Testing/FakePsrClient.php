<?php

declare(strict_types=1);

namespace PlinCode\JobBoards\Testing;

use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response as PsrResponse;
use PlinCode\JobBoards\Http\HttpClient;
use PlinCode\JobBoards\Http\SupportsTimeout;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 * A PSR-18 client that answers from a queue and records what it was asked for.
 * This is the replacement for Laravel's Http::fake(): queue the responses the
 * provider would give, then assert on ->uris() afterwards.
 *
 * It also implements SupportsTimeout so tests can assert that the connector
 * asked for the timeout it says it does.
 */
final class FakePsrClient implements ClientInterface, SupportsTimeout
{
    /** @var list<RequestInterface> */
    public array $requests = [];

    /** @var list<float> */
    public array $appliedTimeouts = [];

    /** @var list<ResponseInterface> */
    private array $queue = [];

    private bool $throwNetworkError = false;

    /** @var list<array{match: string, response: ?ResponseInterface}> */
    private array $matched = [];

    /**
     * @param  array<string, string|list<string>>  $headers
     */
    public function respondWith(int $status, string $body = '', array $headers = []): self
    {
        $this->queue[] = new PsrResponse($status, $headers, $body);

        return $this;
    }

    /**
     * @param  array<array-key, mixed>  $payload
     */
    public function respondWithJson(array $payload, int $status = 200): self
    {
        return $this->respondWith($status, json_encode($payload, JSON_THROW_ON_ERROR), ['Content-Type' => 'application/json']);
    }

    public function throwNetworkError(): self
    {
        $this->throwNetworkError = true;

        return $this;
    }

    /**
     * Answer a request whose URI contains $match with this response, regardless of
     * queue order. Connectors that hit more than one host (a region fallback, a
     * separate careers page) otherwise have to build a FIFO queue and hope the
     * order is what they think it is.
     *
     * @param  array<string, string|list<string>>  $headers
     */
    public function respondWhen(string $match, int $status, string $body = '', array $headers = []): self
    {
        $this->matched[] = ['match' => $match, 'response' => new PsrResponse($status, $headers, $body)];

        return $this;
    }

    /**
     * @param  array<array-key, mixed>  $payload
     */
    public function respondWhenJson(string $match, array $payload, int $status = 200): self
    {
        return $this->respondWhen($match, $status, json_encode($payload, JSON_THROW_ON_ERROR), ['Content-Type' => 'application/json']);
    }

    /**
     * Fail only the requests whose URI contains $match. This is what makes
     * "the first host is unreachable, the second answers" testable at all.
     */
    public function throwNetworkErrorWhen(string $match): self
    {
        $this->matched[] = ['match' => $match, 'response' => null];

        return $this;
    }

    public function withTimeout(float $seconds): ClientInterface
    {
        $this->appliedTimeouts[] = $seconds;

        return $this;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;

        if ($this->throwNetworkError) {
            throw new FakeNetworkException($request);
        }

        $uri = (string) $request->getUri();

        foreach ($this->matched as $rule) {
            if (! str_contains($uri, $rule['match'])) {
                continue;
            }

            if ($rule['response'] === null) {
                throw new FakeNetworkException($request);
            }

            return $rule['response'];
        }

        $next = array_shift($this->queue);

        if ($next === null) {
            throw new RuntimeException('No queued response for '.$request->getUri());
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

    /**
     * Core's HttpClient wired to this fake.
     */
    public function asHttpClient(): HttpClient
    {
        return new HttpClient($this, new HttpFactory);
    }
}

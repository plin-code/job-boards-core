<?php

declare(strict_types=1);

namespace PlinCode\JobBoards\Http;

use GuzzleHttp\Client as Guzzle;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Guzzle's PSR-18 client cannot change its timeout after construction, so
 * HttpClient::withTimeout() has nothing to call and the configured value is
 * silently ignored. This wrapper builds a fresh Guzzle for each timeout, which
 * is what makes a per-call timeout (a short one for validateSlug, a longer one
 * for a full fetch) actually take effect.
 *
 * Requires guzzlehttp/guzzle. Consumers on another PSR-18 client should
 * implement SupportsTimeout themselves rather than use this.
 */
final class GuzzleTimeoutClient implements ClientInterface, SupportsTimeout
{
    private ClientInterface $client;

    /**
     * @param  array<string, mixed>  $options  passed through to the Guzzle constructor
     */
    public function __construct(
        private readonly array $options = [],
        private readonly ?float $timeout = null,
    ) {
        $this->client = new Guzzle($this->guzzleOptions());
    }

    public function withTimeout(float $seconds): ClientInterface
    {
        return new self($this->options, $seconds);
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        return $this->client->sendRequest($request);
    }

    /**
     * @return array<string, mixed>
     */
    private function guzzleOptions(): array
    {
        $options = $this->options;

        if ($this->timeout !== null) {
            $options['timeout'] = $this->timeout;
            $options['connect_timeout'] ??= $this->timeout;
        }

        // A 4xx or 5xx is data a connector inspects, never an exception.
        $options['http_errors'] = false;

        return $options;
    }
}

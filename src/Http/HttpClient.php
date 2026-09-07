<?php

declare(strict_types=1);

namespace PlinCode\JobBoards\Http;

use PlinCode\JobBoards\Exceptions\TransportException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * A thin, terse wrapper over PSR-18 for the handful of things job board
 * connectors actually do: GET a URL with a query string, then read the body as
 * text or as JSON.
 *
 * The one rule this class enforces is the separation a connector cares about:
 * a non 2xx answer is a Response you inspect, a request that never completed is
 * a TransportException (or a null from tryGet()).
 */
final class HttpClient
{
    /**
     * @param  array<string, string>  $headers
     */
    public function __construct(
        private readonly ClientInterface $client,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly ?LoggerInterface $logger = null,
        private readonly ?float $timeout = null,
        private readonly array $headers = [],
    ) {}

    /**
     * The timeout requested for outgoing requests, if any. Only honoured when the
     * injected PSR-18 client implements {@see SupportsTimeout}; see the README.
     */
    public function timeout(): ?float
    {
        return $this->timeout;
    }

    public function withTimeout(?float $seconds): self
    {
        return new self($this->client, $this->requestFactory, $this->logger, $seconds, $this->headers);
    }

    /**
     * @param  array<string, string>  $headers
     */
    public function withHeaders(array $headers): self
    {
        return new self(
            $this->client,
            $this->requestFactory,
            $this->logger,
            $this->timeout,
            [...$this->headers, ...$headers],
        );
    }

    public function withLogger(?LoggerInterface $logger): self
    {
        return new self($this->client, $this->requestFactory, $logger, $this->timeout, $this->headers);
    }

    /**
     * GET $url with $query appended. Null query values are dropped.
     *
     * @param  array<string, string|int|float|bool|null>  $query
     *
     * @throws TransportException when no response could be obtained at all
     */
    public function get(string $url, array $query = []): Response
    {
        $target = self::buildUrl($url, $query);

        $request = $this->requestFactory->createRequest('GET', $target);

        foreach ($this->headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        $client = $this->client;

        if ($this->timeout !== null && $client instanceof SupportsTimeout) {
            $client = $client->withTimeout($this->timeout);
        }

        try {
            $response = $client->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw TransportException::requestFailed($target, $e);
        }

        return new Response($target, $response->getStatusCode(), (string) $response->getBody());
    }

    /**
     * Same as get(), but a transport failure returns null instead of throwing,
     * which is what a connector that logs and moves on to the next company wants.
     * A non 2xx response is still returned: only the caller knows what it means.
     *
     * @param  array<string, string|int|float|bool|null>  $query
     */
    public function tryGet(string $url, array $query = []): ?Response
    {
        try {
            return $this->get($url, $query);
        } catch (TransportException $e) {
            $this->logger?->error($e->getMessage(), ['url' => $url, 'exception' => $e]);

            return null;
        }
    }

    /**
     * @param  array<string, string|int|float|bool|null>  $query
     */
    private static function buildUrl(string $url, array $query): string
    {
        if ($query === []) {
            return $url;
        }

        $string = http_build_query($query, '', '&', PHP_QUERY_RFC3986);

        if ($string === '') {
            return $url;
        }

        return $url.(str_contains($url, '?') ? '&' : '?').$string;
    }
}

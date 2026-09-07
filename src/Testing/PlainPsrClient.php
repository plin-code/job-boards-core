<?php

declare(strict_types=1);

namespace PlinCode\JobBoards\Testing;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * A PSR-18 client that deliberately does NOT implement SupportsTimeout, so a
 * test can assert what happens on the majority of real clients: the timeout is
 * recorded by HttpClient and never reaches the transport.
 */
final class PlainPsrClient implements ClientInterface
{
    public function __construct(private readonly FakePsrClient $inner = new FakePsrClient) {}

    public function respondWith(int $status, string $body = ''): self
    {
        $this->inner->respondWith($status, $body);

        return $this;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        return $this->inner->sendRequest($request);
    }
}

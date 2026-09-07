<?php

declare(strict_types=1);

namespace PlinCode\JobBoards\Http;

use Psr\Http\Client\ClientInterface;

/**
 * PSR-18 has no notion of a timeout, so there is nothing standard for
 * {@see HttpClient::withTimeout()} to call. A PSR-18 client that can produce a
 * copy of itself with a different timeout may advertise it by implementing this
 * interface; HttpClient will then honour per-request timeouts. Clients that do
 * not implement it keep whatever timeout they were built with.
 */
interface SupportsTimeout
{
    /**
     * Return a client identical to this one but with the given timeout, in seconds.
     */
    public function withTimeout(float $seconds): ClientInterface;
}

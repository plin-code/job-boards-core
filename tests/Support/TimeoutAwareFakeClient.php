<?php

declare(strict_types=1);

namespace PlinCode\JobBoards\Tests\Support;

use PlinCode\JobBoards\Http\SupportsTimeout;
use Psr\Http\Client\ClientInterface;

final class TimeoutAwareFakeClient extends FakePsrClient implements SupportsTimeout
{
    public ?float $appliedTimeout = null;

    public function withTimeout(float $seconds): ClientInterface
    {
        $this->appliedTimeout = $seconds;

        return $this;
    }
}

<p align="center">
  <img src="https://raw.githubusercontent.com/plin-code/job-boards-core/main/art/banner.png" alt="Job Boards Core">
</p>

# Job Boards Core

<p align="center">
    <a href="https://packagist.org/packages/plin-code/job-boards-core"><img src="https://img.shields.io/packagist/v/plin-code/job-boards-core.svg?style=flat-square" alt="Packagist"></a>
    <a href="https://packagist.org/packages/plin-code/job-boards-core"><img src="https://img.shields.io/packagist/php-v/plin-code/job-boards-core.svg?style=flat-square" alt="PHP from Packagist"></a>
    <a href="https://packagist.org/packages/plin-code/job-boards-core"><img src="https://img.shields.io/packagist/dt/plin-code/job-boards-core.svg?style=flat-square" alt="Total Downloads"></a>
</p>

Shared foundation for the `plin-code` job board connector packages.

It holds the four things every connector needs and nothing else:

- `JobBoardClient`, the contract a connector implements.
- `JobPostingDTO`, the shape a connector returns.
- A small exception hierarchy.
- A thin PSR-18 helper so connectors do not each hand roll HTTP.

The package is framework agnostic: no Laravel, no service provider, no facades, no logging.

## This package is not useful on its own

There is nothing to run here. Install it because you are writing (or using) a connector such as
`plin-code/job-boards-personio` or `plin-code/job-boards-smartrecruiters`. Those packages depend on
this one; an application normally installs them, not this.

## Installation

```bash
composer require plin-code/job-boards-core
```

Requires PHP 8.3 or newer.

## Bring your own HTTP client

This package depends on the PSR-18 client interface and the PSR-17 request factory interface, never on
an implementation. You inject the client you already have.

In a Laravel application, Guzzle is already installed and `GuzzleHttp\Client` is a PSR-18 client, while
`GuzzleHttp\Psr7\HttpFactory` is a PSR-17 factory:

```php
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use PlinCode\JobBoards\Http\HttpClient;

$http = new HttpClient(
    client: new Client(['timeout' => 30]),
    requestFactory: new HttpFactory,
    logger: Log::channel('stack'), // optional, see "Logging" below
);
```

In a Symfony application, `Symfony\Component\HttpClient\Psr18Client` is both:

```php
use PlinCode\JobBoards\Http\HttpClient;
use Symfony\Component\HttpClient\Psr18Client;

$psr18 = new Psr18Client;

$http = new HttpClient(client: $psr18, requestFactory: $psr18);
```

Any other PSR-18 client works the same way.

## Implementing a connector

```php
use PlinCode\JobBoards\Contracts\JobBoardClient;
use PlinCode\JobBoards\Data\JobPostingDTO;
use PlinCode\JobBoards\Http\HttpClient;

final class AcmeJobBoardClient implements JobBoardClient
{
    public function __construct(private readonly HttpClient $http) {}

    /**
     * @return list<JobPostingDTO>
     */
    public function fetchJobsForCompany(string $slug): array
    {
        $response = $this->http->tryGet("https://api.acme.test/{$slug}/jobs", ['limit' => 100]);

        if ($response === null || ! $response->successful()) {
            return [];
        }

        $jobs = [];

        foreach ($response->jsonArray('content') as $job) {
            if (! is_array($job)) {
                continue;
            }

            $jobs[] = new JobPostingDTO(
                externalId: (string) $job['id'],
                title: (string) $job['title'],
                location: $job['location'] ?? null,
                url: (string) $job['url'],
                department: $job['department'] ?? null,
                rawPayload: $job,
            );
        }

        return $jobs;
    }

    public function validateSlug(string $slug): ?string
    {
        $response = $this->http->withTimeout(15.0)->tryGet("https://api.acme.test/{$slug}");

        return $response?->successful() ? (string) $response->json('name') : null;
    }

    public function fetchCompanyDescription(string $slug): ?string
    {
        return null;
    }
}
```

## The HTTP helper

`PlinCode\JobBoards\Http\HttpClient` does the handful of things a job board connector actually does.

| Method | Behaviour |
| --- | --- |
| `get(string $url, array $query = []): Response` | GET with the query appended. Throws `TransportException` when no response could be obtained. |
| `tryGet(string $url, array $query = []): ?Response` | Same, but returns `null` on a transport failure instead of throwing. |
| `withTimeout(?float $seconds): self` | Copy with a timeout. See the caveat below. |
| `withHeaders(array $headers): self` | Copy with extra request headers. |
| `withLogger(?LoggerInterface $logger): self` | Copy with a PSR-3 logger. |
| `timeout(): ?float` | The timeout currently requested, if any. |

Query values that are `null` are dropped, so an optional parameter can be expressed inline:

```php
$this->http->tryGet($url, ['language' => $inEnglish ? 'en' : null]);
```

`Response` is what came back, whatever the status:

| Method | Behaviour |
| --- | --- |
| `status(): int` | The HTTP status code. |
| `successful(): bool` / `failed(): bool` | Whether the status is 2xx. |
| `body(): string` | The raw body, untouched. XML boards read this. |
| `json(?string $key = null, mixed $default = null): mixed` | The decoded body, or one value addressed with dot notation (`company.name`). |
| `jsonArray(?string $key = null): array` | The same, but insists on an array so callers get a typed result. |

### Failure is split in two on purpose

A connector treats "the board answered 404" and "the board did not answer" differently, so the helper
keeps them apart:

- **A response arrived**, whatever the status: you get a `Response`. A non 2xx is not an exception,
  because only the connector knows whether a 404 means "no such company" or "something broke".
- **No response arrived** (DNS, refused connection, TLS, timeout): `get()` throws
  `TransportException`, `tryGet()` returns `null`.
- **A response arrived but the body is unreadable** as JSON: `json()` throws
  `InvalidResponseException`. A 200 carrying an HTML error page is a bug, not a result.

Both exceptions implement `PlinCode\JobBoards\Exceptions\JobBoardException`, so a consumer can catch
the whole family at once.

### Timeouts

PSR-18 has no timeout abstraction. There is no standard way to tell an arbitrary PSR-18 client
"take at most 15 seconds for this request", and this package will not pretend otherwise.

So the timeout normally lives on the client you inject (`new GuzzleHttp\Client(['timeout' => 30])`),
and `withTimeout()` is a no-op for such a client. `timeout()` still reports the value you asked for.

If you need per-request timeouts, which some boards do (a short one for slug validation, a longer one
for a full sync), implement `PlinCode\JobBoards\Http\SupportsTimeout` on the client you inject. When
the injected client advertises that interface, `HttpClient` calls it before each request:

```php
use GuzzleHttp\Client;
use PlinCode\JobBoards\Http\SupportsTimeout;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class GuzzlePsr18Client implements ClientInterface, SupportsTimeout
{
    public function __construct(private readonly float $timeout = 30.0) {}

    public function withTimeout(float $seconds): ClientInterface
    {
        return new self($seconds);
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        return (new Client(['timeout' => $this->timeout, 'http_errors' => false]))->send($request);
    }
}
```

## Logging

**This package never logs.** Logging is a policy decision, and a library that writes to a channel it
chose itself is a nuisance in someone else's application.

Instead:

1. A connector reports a soft failure through its return value. `fetchJobsForCompany()` returns an
   empty array, `validateSlug()` and `fetchCompanyDescription()` return `null`. A daily sync across
   many companies must not abort because one board is down.
2. If you want a record of *why*, inject a PSR-3 `LoggerInterface`. It is optional and defaults to
   `null`; the helper logs exactly one thing, a transport failure swallowed by `tryGet()`.

`psr/log` is an interface-only package with no dependencies, and Laravel's logger already implements
`LoggerInterface`, so `Log::channel('stack')` can be passed straight in. The alternative, inventing an
event or callback abstraction, would force every consumer to write an adapter for something PSR-3
already standardises.

## Testing

```bash
composer install
composer test        # Pest
composer analyse     # PHPStan
composer format      # Pint
```

## License

MIT. See [LICENSE.md](LICENSE.md).

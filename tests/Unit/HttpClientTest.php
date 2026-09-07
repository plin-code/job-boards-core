<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\HttpFactory;
use PlinCode\JobBoards\Exceptions\InvalidResponseException;
use PlinCode\JobBoards\Exceptions\TransportException;
use PlinCode\JobBoards\Http\HttpClient;
use PlinCode\JobBoards\Testing\FakePsrClient;
use PlinCode\JobBoards\Testing\PlainPsrClient;
use PlinCode\JobBoards\Testing\RecordingLogger;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;

function http(ClientInterface $fake, ?RecordingLogger $logger = null): HttpClient
{
    return new HttpClient($fake, new HttpFactory, $logger);
}

it('sends a plain get when there is no query', function (): void {
    $fake = (new FakePsrClient)->respondWith(200, 'ok');

    $response = http($fake)->get('https://example.test/jobs');

    expect($fake->lastUri())->toBe('https://example.test/jobs')
        ->and($fake->requests[0]->getMethod())->toBe('GET')
        ->and($response->status())->toBe(200)
        ->and($response->body())->toBe('ok');
});

it('appends query parameters', function (): void {
    $fake = (new FakePsrClient)->respondWith(200, '{}');

    http($fake)->get('https://api.example.test/postings', ['offset' => 0, 'limit' => 100]);

    expect($fake->lastUri())->toBe('https://api.example.test/postings?offset=0&limit=100');
});

it('merges into a url that already has a query string', function (): void {
    $fake = (new FakePsrClient)->respondWith(200, '{}');

    http($fake)->get('https://example.test/x?a=1', ['language' => 'en']);

    expect($fake->lastUri())->toBe('https://example.test/x?a=1&language=en');
});

it('rfc3986 encodes query values', function (): void {
    $fake = (new FakePsrClient)->respondWith(200, '{}');

    http($fake)->get('https://example.test/x', ['q' => 'senior engineer', 'tag' => 'a&b=c']);

    expect($fake->lastUri())->toBe('https://example.test/x?q=senior%20engineer&tag=a%26b%3Dc');
});

it('drops null query values so an optional parameter can be omitted inline', function (): void {
    $fake = (new FakePsrClient)->respondWith(200, '{}');

    http($fake)->get('https://example.test/x', ['language' => null]);

    expect($fake->lastUri())->toBe('https://example.test/x');
});

it('sends configured headers', function (): void {
    $fake = (new FakePsrClient)->respondWith(200, '{}');

    http($fake)->withHeaders(['Accept' => 'application/json'])->get('https://example.test/x');

    expect($fake->requests[0]->getHeaderLine('Accept'))->toBe('application/json');
});

it('returns a non 2xx response instead of throwing', function (int $status): void {
    $fake = (new FakePsrClient)->respondWith($status, 'nope');

    $response = http($fake)->get('https://example.test/x');

    expect($response->successful())->toBeFalse()
        ->and($response->status())->toBe($status)
        ->and($response->body())->toBe('nope');
})->with([404, 429, 500, 503]);

it('throws a transport exception when the request never completes', function (): void {
    $fake = (new FakePsrClient)->throwNetworkError();

    http($fake)->get('https://example.test/x');
})->throws(TransportException::class);

it('names the url in the transport exception and keeps the psr-18 exception', function (): void {
    $fake = (new FakePsrClient)->throwNetworkError();

    try {
        http($fake)->get('https://example.test/x', ['a' => 1]);

        throw new LogicException('Expected a TransportException.');
    } catch (TransportException $e) {
        expect($e->getMessage())->toContain('https://example.test/x?a=1')
            ->and($e->getPrevious())->toBeInstanceOf(ClientExceptionInterface::class);
    }
});

it('returns null from tryGet on a transport failure', function (): void {
    $fake = (new FakePsrClient)->throwNetworkError();

    expect(http($fake)->tryGet('https://example.test/x'))->toBeNull();
});

it('still returns non 2xx responses from tryGet, since only the caller knows what they mean', function (): void {
    $fake = (new FakePsrClient)->respondWith(404, '');

    $response = http($fake)->tryGet('https://example.test/x');

    expect($response)->not->toBeNull()
        ->and($response?->status())->toBe(404);
});

it('reports a transport failure to an injected psr-3 logger', function (): void {
    $fake = (new FakePsrClient)->throwNetworkError();
    $logger = new RecordingLogger;

    http($fake, $logger)->tryGet('https://example.test/x');

    expect($logger->records)->toHaveCount(1)
        ->and($logger->records[0]['level'])->toBe('error')
        ->and($logger->records[0]['message'])->toContain('https://example.test/x')
        ->and($logger->records[0]['context']['url'])->toBe('https://example.test/x');
});

it('works without a logger', function (): void {
    $fake = (new FakePsrClient)->throwNetworkError();

    expect(http($fake)->tryGet('https://example.test/x'))->toBeNull();
});

it('never logs a response that arrived, whatever its status', function (): void {
    $fake = (new FakePsrClient)->respondWith(500, 'boom');
    $logger = new RecordingLogger;

    http($fake, $logger)->tryGet('https://example.test/x');

    expect($logger->records)->toBeEmpty();
});

it('decodes json from the response', function (): void {
    $fake = (new FakePsrClient)->respondWith(200, '{"content":[{"id":1}],"totalFound":1}');

    $response = http($fake)->get('https://example.test/x');

    expect($response->json('content'))->toBe([['id' => 1]])
        ->and($response->json('totalFound'))->toBe(1);
});

it('throws an invalid response exception on malformed json', function (): void {
    $fake = (new FakePsrClient)->respondWith(200, '{"content": [');

    http($fake)->get('https://example.test/x')->json();
})->throws(InvalidResponseException::class);

it('decodes an empty 200 body to null rather than throwing', function (): void {
    $fake = (new FakePsrClient)->respondWith(200, '');

    expect(http($fake)->get('https://example.test/x')->json())->toBeNull();
});

it('leaves an empty body readable as a string', function (): void {
    $fake = (new FakePsrClient)->respondWith(200, '');

    expect(http($fake)->get('https://example.test/x')->body())->toBe('');
});

it('carries no timeout by default', function (): void {
    expect(http(new FakePsrClient)->timeout())->toBeNull();
});

it('applies the timeout when the injected client advertises support for it', function (): void {
    $fake = (new FakePsrClient)->respondWith(200, '{}');

    http($fake)->withTimeout(15.0)->get('https://example.test/x');

    expect($fake->appliedTimeouts)->toContain(15.0);
});

it('ignores the timeout when the injected client cannot honour it', function (): void {
    $fake = (new PlainPsrClient)->respondWith(200, '{}');

    $client = http($fake)->withTimeout(15.0);

    expect($client->timeout())->toBe(15.0)
        ->and($client->get('https://example.test/x')->status())->toBe(200);
});

it('returns a new instance from every with* method', function (): void {
    $client = http(new FakePsrClient);

    expect($client->withTimeout(5.0))->not->toBe($client)
        ->and($client->withHeaders(['A' => 'b']))->not->toBe($client)
        ->and($client->withLogger(new RecordingLogger))->not->toBe($client)
        ->and($client->timeout())->toBeNull();
});

it('supports the per-call timeouts a connector needs for fetch versus validate', function (): void {
    $fake = (new FakePsrClient)->respondWith(200, '{}')->respondWith(200, '{}');
    $client = http($fake)->withTimeout(30.0);

    $client->get('https://example.test/jobs');
    expect($fake->appliedTimeouts)->toContain(30.0);

    $client->withTimeout(15.0)->get('https://example.test/jobs');
    expect($fake->appliedTimeouts)->toContain(15.0);
});

it('drives a paginated board the way a connector would', function (): void {
    $page = fn (int $from): string => json_encode([
        'content' => [['id' => $from], ['id' => $from + 1]],
        'totalFound' => 4,
    ], JSON_THROW_ON_ERROR);

    $fake = (new FakePsrClient)->respondWith(200, $page(1))->respondWith(200, $page(3));
    $client = http($fake);

    $postings = [];
    $offset = 0;

    do {
        $response = $client->get('https://api.example.test/postings', ['offset' => $offset, 'limit' => 2]);
        expect($response->successful())->toBeTrue();

        $postings = [...$postings, ...$response->jsonArray('content')];
        $found = $response->json('totalFound');
        $total = is_int($found) ? $found : 0;
        $offset += 2;
    } while ($offset < $total);

    expect($postings)->toHaveCount(4)
        ->and($fake->uris())->toBe([
            'https://api.example.test/postings?offset=0&limit=2',
            'https://api.example.test/postings?offset=2&limit=2',
        ]);
});

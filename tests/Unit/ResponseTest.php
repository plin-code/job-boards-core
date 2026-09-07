<?php

declare(strict_types=1);

use PlinCode\JobBoards\Exceptions\InvalidResponseException;
use PlinCode\JobBoards\Http\Response;

function response(string $body, int $status = 200): Response
{
    return new Response('https://example.test/x', $status, $body);
}

it('reports success only for 2xx', function (int $status, bool $successful): void {
    expect(response('{}', $status)->successful())->toBe($successful)
        ->and(response('{}', $status)->failed())->toBe(! $successful);
})->with([
    [199, false],
    [200, true],
    [204, true],
    [299, true],
    [300, false],
    [404, false],
    [500, false],
]);

it('returns the raw body untouched', function (): void {
    $xml = "<?xml version=\"1.0\"?>\n<workzag-jobs><position><id>1</id></position></workzag-jobs>";

    expect(response($xml)->body())->toBe($xml);
});

it('decodes the whole json body', function (): void {
    expect(response('{"content":[{"id":1}],"totalFound":1}')->json())
        ->toBe(['content' => [['id' => 1]], 'totalFound' => 1]);
});

it('reads a json value by key', function (): void {
    expect(response('{"content":[{"id":1}]}')->json('content'))->toBe([['id' => 1]]);
});

it('reads a nested json value with dot notation', function (): void {
    expect(response('{"company":{"name":"ACME"}}')->json('company.name'))->toBe('ACME');
});

it('returns the default for a missing key instead of throwing', function (): void {
    expect(response('{"content":[]}')->json('missing'))->toBeNull()
        ->and(response('{"content":[]}')->json('missing', 'fallback'))->toBe('fallback')
        ->and(response('{"company":{}}')->json('company.name', 'fallback'))->toBe('fallback')
        ->and(response('{"company":"acme"}')->json('company.name', 'fallback'))->toBe('fallback');
});

it('decodes the body only once', function (): void {
    $response = response('{"a":1}');

    expect($response->json())->toBe(['a' => 1])
        ->and($response->json('a'))->toBe(1);
});

it('throws on malformed json', function (): void {
    response('{"content": [')->json();
})->throws(InvalidResponseException::class, 'not valid JSON');

it('throws on an html error page served with a 200', function (): void {
    response('<html><body>Oops</body></html>')->json();
})->throws(InvalidResponseException::class);

it('throws on an empty body', function (): void {
    response('')->json();
})->throws(InvalidResponseException::class, 'empty body');

it('treats a whitespace only body as empty', function (): void {
    response("\n \t ")->json();
})->throws(InvalidResponseException::class, 'empty body');

it('returns a typed array from jsonArray', function (): void {
    expect(response('{"content":[1,2]}')->jsonArray('content'))->toBe([1, 2])
        ->and(response('[1,2]')->jsonArray())->toBe([1, 2]);
});

it('throws when jsonArray finds something other than an array', function (): void {
    response('{"content":"nope"}')->jsonArray('content');
})->throws(InvalidResponseException::class, 'Expected an array at "content"');

it('throws when jsonArray finds a scalar at the root', function (): void {
    response('42')->jsonArray();
})->throws(InvalidResponseException::class, 'got int');

it('exposes the url it was fetched from', function (): void {
    expect(response('{}')->url())->toBe('https://example.test/x');
});

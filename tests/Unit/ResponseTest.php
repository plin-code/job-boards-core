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

it('decodes an empty body to null rather than throwing', function (): void {
    expect(response('')->json())->toBeNull();
});

it('honours the default on an empty body', function (): void {
    expect(response('', 204)->json('anything', 'FALLBACK'))->toBe('FALLBACK');
});

it('treats a whitespace only body as empty', function (): void {
    expect(response("\n \t ")->json())->toBeNull();
});

it('still rejects an empty body from jsonArray', function (): void {
    response('')->jsonArray();
})->throws(InvalidResponseException::class);

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

it('does not throw from tryJson on a body that is not json', function (): void {
    expect(response('<html>maintenance</html>')->tryJson())->toBeNull()
        ->and(response('<html>maintenance</html>')->tryJson('a.b', 'FALLBACK'))->toBe('FALLBACK');
});

it('still decodes normally through tryJson', function (): void {
    expect(response('{"company":{"name":"Laravel"}}')->tryJson('company.name'))->toBe('Laravel');
});

it('reads a nested value without an is_array guard', function (): void {
    $r = response('{"content":[{"company":{"name":"ABOUT YOU"}}]}');

    expect($r->nested('content.0.company.name'))->toBe('ABOUT YOU')
        ->and($r->nested('content.0.company.missing', 'none'))->toBe('none')
        ->and($r->nested('content.0.company.name.deeper', 'none'))->toBe('none');
});

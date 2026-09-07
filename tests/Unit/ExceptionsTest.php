<?php

declare(strict_types=1);

use PlinCode\JobBoards\Exceptions\InvalidResponseException;
use PlinCode\JobBoards\Exceptions\JobBoardException;
use PlinCode\JobBoards\Exceptions\TransportException;

it('roots every exception at the package marker interface', function (string $class): void {
    expect(is_subclass_of($class, JobBoardException::class))->toBeTrue()
        ->and(is_subclass_of($class, Throwable::class))->toBeTrue();
})->with([TransportException::class, InvalidResponseException::class]);

it('keeps the underlying transport error as the previous exception', function (): void {
    $cause = new RuntimeException('Could not resolve host');

    $exception = TransportException::requestFailed('https://example.test/x', $cause);

    expect($exception->getMessage())->toContain('https://example.test/x')
        ->and($exception->getMessage())->toContain('Could not resolve host')
        ->and($exception->getPrevious())->toBe($cause);
});

it('describes an empty body', function (): void {
    expect(InvalidResponseException::emptyBody('https://example.test/x')->getMessage())
        ->toContain('empty body')
        ->toContain('https://example.test/x');
});

it('describes malformed json and keeps the json error', function (): void {
    $cause = new JsonException('Syntax error');

    $exception = InvalidResponseException::malformedJson('https://example.test/x', $cause);

    expect($exception->getMessage())->toContain('not valid JSON')
        ->and($exception->getPrevious())->toBe($cause);
});

it('describes an unexpected shape with the path and the actual type', function (): void {
    $exception = InvalidResponseException::unexpectedShape('https://example.test/x', 'content', 'string');

    expect($exception->getMessage())
        ->toContain('content')
        ->toContain('string');
});

it('can be caught as a single family', function (): void {
    $caught = null;

    try {
        throw InvalidResponseException::emptyBody('https://example.test/x');
    } catch (JobBoardException $e) {
        $caught = $e;
    }

    expect($caught)->toBeInstanceOf(InvalidResponseException::class);
});

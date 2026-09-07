<?php

declare(strict_types=1);

use PlinCode\JobBoards\Data\JobPostingDTO;

it('exposes the constructor values as readonly properties', function (): void {
    $dto = new JobPostingDTO(
        externalId: '123',
        title: 'Backend Engineer',
        location: 'Milan, IT',
        url: 'https://example.test/jobs/123',
        department: 'Engineering',
        rawPayload: ['id' => 123, 'nested' => ['a' => 1]],
    );

    expect($dto->externalId)->toBe('123')
        ->and($dto->title)->toBe('Backend Engineer')
        ->and($dto->location)->toBe('Milan, IT')
        ->and($dto->url)->toBe('https://example.test/jobs/123')
        ->and($dto->department)->toBe('Engineering')
        ->and($dto->rawPayload)->toBe(['id' => 123, 'nested' => ['a' => 1]]);
});

it('maps to the snake_case keys consumers persist', function (): void {
    $dto = new JobPostingDTO('123', 'Backend Engineer', 'Milan', 'https://example.test/1', 'Engineering', ['k' => 'v']);

    expect($dto->toArray())->toBe([
        'external_id' => '123',
        'title' => 'Backend Engineer',
        'location' => 'Milan',
        'url' => 'https://example.test/1',
        'department' => 'Engineering',
        'raw_payload' => ['k' => 'v'],
    ]);
});

it('keeps the array keys and their order stable', function (): void {
    $dto = new JobPostingDTO('1', 'T', null, 'u', null, []);

    expect(array_keys($dto->toArray()))
        ->toBe(['external_id', 'title', 'location', 'url', 'department', 'raw_payload']);
});

it('allows a null location and department', function (): void {
    $dto = new JobPostingDTO('1', 'Untitled Position', null, 'https://example.test/1', null, []);

    expect($dto->toArray())->toMatchArray([
        'location' => null,
        'department' => null,
        'raw_payload' => [],
    ]);
});

it('is immutable', function (): void {
    expect((new ReflectionClass(JobPostingDTO::class))->isReadOnly())->toBeTrue();
});

<?php

declare(strict_types=1);

use PlinCode\JobBoards\Http\GuzzleTimeoutClient;
use PlinCode\JobBoards\Http\SupportsTimeout;
use PlinCode\JobBoards\Testing\FakePsrClient;
use PlinCode\JobBoards\Testing\RecordingLogger;

it('ships the psr-18 double every connector needs', function (): void {
    $fake = (new FakePsrClient)->respondWithJson(['name' => 'Laravel']);

    expect($fake->asHttpClient()->get('https://example.test/x')->json('name'))->toBe('Laravel')
        ->and($fake->lastUri())->toBe('https://example.test/x');
});

it('ships a recording logger', function (): void {
    $logger = new RecordingLogger;
    $logger->warning('boom', ['slug' => 'x']);

    expect($logger->messages())->toBe(['boom'])
        ->and($logger->levels())->toBe(['warning']);
});

it('makes guzzle honour a per-call timeout', function (): void {
    $client = new GuzzleTimeoutClient;

    expect($client)->toBeInstanceOf(SupportsTimeout::class)
        ->and($client->withTimeout(15.0))->not->toBe($client)
        ->and($client->withTimeout(15.0))->toBeInstanceOf(GuzzleTimeoutClient::class);
});

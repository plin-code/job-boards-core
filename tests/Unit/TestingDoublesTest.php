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

it('answers a specific host regardless of queue order', function (): void {
    $fake = (new FakePsrClient)
        ->respondWhenJson('jobs.eu.lever.co', ['eu' => true])
        ->respondWhenJson('api.lever.co', ['us' => true]);

    $http = $fake->asHttpClient();

    expect($http->get('https://api.lever.co/v0/postings/x')->json('us'))->toBeTrue()
        ->and($http->get('https://jobs.eu.lever.co/v0/postings/x')->json('eu'))->toBeTrue();
});

it('fails one host and answers on the other', function (): void {
    $fake = (new FakePsrClient)
        ->throwNetworkErrorWhen('api.lever.co')
        ->respondWhenJson('jobs.eu.lever.co', ['region' => 'eu']);

    $http = $fake->asHttpClient();

    expect($http->tryGet('https://api.lever.co/v0/postings/x'))->toBeNull()
        ->and($http->get('https://jobs.eu.lever.co/v0/postings/x')->json('region'))->toBe('eu');
});

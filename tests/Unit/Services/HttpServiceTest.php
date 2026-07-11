<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Whatsdiff\Services\CacheService;
use Whatsdiff\Services\ConfigService;
use Whatsdiff\Services\GithubAuthService;
use Whatsdiff\Services\HttpService;

function buildHttpService(MockHandler $mock, array &$history, ?string $token = null): HttpService
{
    $stack = HandlerStack::create($mock);
    $stack->push(Middleware::history($history));

    $cacheDir = sys_get_temp_dir().'/whatsdiff-http-test-'.bin2hex(random_bytes(5));
    $cache = new CacheService(new ConfigService($cacheDir.'/config.json'), $cacheDir);
    $cache->disableCache();

    return new HttpService(
        $cache,
        new GithubAuthService($token),
        new Client(['handler' => $stack, 'http_errors' => false]),
    );
}

it('uses an injected client instead of building its own', function () {
    $history = [];
    $service = buildHttpService(new MockHandler([new Response(200, [], 'mock-body')]), $history);

    expect($service->get('https://packagist.org/anything'))->toBe('mock-body')
        ->and($history)->toHaveCount(1)
        ->and((string) $history[0]['request']->getUri())->toBe('https://packagist.org/anything');
});

it('attaches the github token only to api.github.com requests', function () {
    $history = [];
    $service = buildHttpService(new MockHandler([
        new Response(200, [], '{}'),
        new Response(200, [], '{}'),
    ]), $history, token: 'test-token');

    $service->get('https://api.github.com/advisories');
    $service->get('https://packagist.org/api/security-advisories/');

    expect($history[0]['request']->getHeaderLine('Authorization'))->toBe('Bearer test-token')
        ->and($history[1]['request']->hasHeader('Authorization'))->toBe(false);
});

it('throws a RuntimeException with the status code on http errors', function () {
    $history = [];
    $service = buildHttpService(new MockHandler([new Response(500, [], 'boom')]), $history);

    $service->get('https://packagist.org/broken');
})->throws(RuntimeException::class, 'HTTP request failed with status code: 500');

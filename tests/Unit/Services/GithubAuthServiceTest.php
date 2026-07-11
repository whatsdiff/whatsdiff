<?php

declare(strict_types=1);

use Whatsdiff\Services\GithubAuthService;

it('prioritizes an explicit constructor token over the environment', function () {
    putenv('GITHUB_TOKEN=env-token');

    try {
        $service = new GithubAuthService('explicit-token');

        expect($service->getToken())->toBe('explicit-token')
            ->and($service->hasToken())->toBe(true);
    } finally {
        putenv('GITHUB_TOKEN');
    }
});

it('falls back to the environment when no explicit token is given', function () {
    putenv('GITHUB_TOKEN=env-token');

    try {
        expect((new GithubAuthService)->getToken())->toBe('env-token');
    } finally {
        putenv('GITHUB_TOKEN');
    }
});

it('normalizes an empty withToken value to the environment fallback', function () {
    putenv('GITHUB_TOKEN=env-token');

    try {
        expect(GithubAuthService::withToken('')->getToken())->toBe('env-token')
            ->and(GithubAuthService::withToken(null)->getToken())->toBe('env-token')
            ->and(GithubAuthService::withToken('abc')->getToken())->toBe('abc');
    } finally {
        putenv('GITHUB_TOKEN');
    }
});

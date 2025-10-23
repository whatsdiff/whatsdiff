<?php

declare(strict_types=1);

use Whatsdiff\Helpers\GithubUrlFormatter;

describe('GithubUrlFormatter::toTerminalLink', function () {
    it('converts GitHub PR URLs to terminal links', function () {
        $input = 'Fix bug in https://github.com/laravel/framework/pull/57207';
        $expected = 'Fix bug in <href=https://github.com/laravel/framework/pull/57207>#57207</>';

        expect(GithubUrlFormatter::toTerminalLink($input))->toBe($expected);
    });

    it('converts GitHub issue URLs to terminal links', function () {
        $input = 'Resolves https://github.com/symfony/symfony/issues/12345';
        $expected = 'Resolves <href=https://github.com/symfony/symfony/issues/12345>#12345</>';

        expect(GithubUrlFormatter::toTerminalLink($input))->toBe($expected);
    });

    it('converts multiple GitHub URLs to terminal links', function () {
        $input = 'Fix https://github.com/owner/repo/pull/100 and https://github.com/owner/repo/issues/200';
        $expected = 'Fix <href=https://github.com/owner/repo/pull/100>#100</> and <href=https://github.com/owner/repo/issues/200>#200</>';

        expect(GithubUrlFormatter::toTerminalLink($input))->toBe($expected);
    });

    it('does not convert URLs already in href attributes', function () {
        $input = '<href=https://github.com/owner/repo/pull/123>link</>';
        $expected = '<href=https://github.com/owner/repo/pull/123>link</>';

        expect(GithubUrlFormatter::toTerminalLink($input))->toBe($expected);
    });

    it('leaves non-GitHub URLs unchanged', function () {
        $input = 'See https://laravel-news.com/article for details';
        $expected = 'See https://laravel-news.com/article for details';

        expect(GithubUrlFormatter::toTerminalLink($input))->toBe($expected);
    });

    it('handles http and https URLs', function () {
        $input = 'Fix http://github.com/owner/repo/pull/123';
        $expected = 'Fix <href=http://github.com/owner/repo/pull/123>#123</>';

        expect(GithubUrlFormatter::toTerminalLink($input))->toBe($expected);
    });
});

describe('GithubUrlFormatter::toMarkdownLink', function () {
    it('converts GitHub PR URLs to markdown links', function () {
        $input = 'Fix bug in https://github.com/laravel/framework/pull/57207';
        $expected = 'Fix bug in [#57207](https://github.com/laravel/framework/pull/57207)';

        expect(GithubUrlFormatter::toMarkdownLink($input))->toBe($expected);
    });

    it('converts GitHub issue URLs to markdown links', function () {
        $input = 'Resolves https://github.com/symfony/symfony/issues/12345';
        $expected = 'Resolves [#12345](https://github.com/symfony/symfony/issues/12345)';

        expect(GithubUrlFormatter::toMarkdownLink($input))->toBe($expected);
    });

    it('converts multiple GitHub URLs to markdown links', function () {
        $input = 'Fix https://github.com/owner/repo/pull/100 and https://github.com/owner/repo/issues/200';
        $expected = 'Fix [#100](https://github.com/owner/repo/pull/100) and [#200](https://github.com/owner/repo/issues/200)';

        expect(GithubUrlFormatter::toMarkdownLink($input))->toBe($expected);
    });

    it('does not convert URLs already in markdown links', function () {
        $input = '[Feature](https://github.com/owner/repo/pull/123)';
        $expected = '[Feature](https://github.com/owner/repo/pull/123)';

        expect(GithubUrlFormatter::toMarkdownLink($input))->toBe($expected);
    });

    it('leaves non-GitHub URLs unchanged', function () {
        $input = 'See https://laravel-news.com/article for details';
        $expected = 'See https://laravel-news.com/article for details';

        expect(GithubUrlFormatter::toMarkdownLink($input))->toBe($expected);
    });

    it('handles http and https URLs', function () {
        $input = 'Fix http://github.com/owner/repo/pull/123';
        $expected = 'Fix [#123](http://github.com/owner/repo/pull/123)';

        expect(GithubUrlFormatter::toMarkdownLink($input))->toBe($expected);
    });
});

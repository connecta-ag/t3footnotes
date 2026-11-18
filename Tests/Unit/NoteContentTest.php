<?php

declare(strict_types=1);

/*
 * This file is part of the t3footnotes TYPO3 extension.
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

namespace CAG\T3footnotes\Tests\Unit;

use CAG\T3footnotes\NoteContent;
use Dom\HTMLDocument;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NoteContentTest extends TestCase
{
    #[DataProvider('urls')]
    public function testValidatesLinkAndImageUrls(string $input, ?string $href, ?string $src): void
    {
        $html = (new NoteContent())->sanitize('<a href="' . $input . '">Example</a><img src="' . $input . '" alt="Example">', false);
        $page = HTMLDocument::createFromString($html, LIBXML_NOERROR, 'UTF-8');
        self::assertSame($href, $page->querySelector('a')?->getAttribute('href'));
        self::assertSame($src, $page->querySelector('img')?->getAttribute('src'));
        self::assertSame($src !== null, $page->querySelector('img') !== null);
    }

    /** @return iterable<string, array{string, ?string, ?string}> */
    public static function urls(): iterable
    {
        yield 'javascript and numeric component' => ['javascript:1/alert(1)//', null, null];
        yield 'mixed case javascript' => ['JavaScript:0/alert(document.domain)//', null, null];
        yield 'vbscript' => ['vbscript:1/x//', null, null];
        yield 'embedded tab' => ['java&#9;script:1/x//', null, null];
        yield 'embedded newline' => ['java&#10;script:1/x//', null, null];
        yield 'leading controls' => ['&#1; JAVASCRIPT:1/x//', null, null];
        yield 'data' => ['data:text/html,example', null, null];
        yield 'unresolved TYPO3 link' => ['t3://page?uid=42', null, null];
        yield 'invalid scheme' => ['1scheme:example/path', null, null];
        yield 'http mixed case' => ['HtTp://example.org:81/file', 'HtTp://example.org:81/file', 'HtTp://example.org:81/file'];
        yield 'normalized https' => [' &#9;hTTps://example.org/fi&#10;le ', 'hTTps://example.org/file', 'hTTps://example.org/file'];
        yield 'mailto' => ['mailto:name@example.org', 'mailto:name@example.org', null];
        yield 'tel' => ['TEL:+41000000000', 'TEL:+41000000000', null];
        yield 'path' => ['notes/item:1', 'notes/item:1', 'notes/item:1'];
        yield 'query' => ['?q=x:y', '?q=x:y', '?q=x:y'];
        yield 'fragment' => ['#x:y', '#x:y', '#x:y'];
        yield 'network reference' => ['//example.org:81/a', '//example.org:81/a', '//example.org:81/a'];
    }

    public function testAutoLinkChecksHttpUrlsAndLeavesOtherSchemesAsText(): void
    {
        $html = (new NoteContent())->sanitize('https://example.org/path. HTTP://example.org:81/file javascript:1/x// https://', true);
        $page = HTMLDocument::createFromString($html, LIBXML_NOERROR, 'UTF-8');
        self::assertSame(2, $page->querySelectorAll('a')->length);
        self::assertSame('https://example.org/path', $page->querySelectorAll('a')[0]->getAttribute('href'));
        self::assertSame('HTTP://example.org:81/file', $page->querySelectorAll('a')[1]->getAttribute('href'));
    }

    public function testFlattenedBlockBoundariesSeparateWords(): void
    {
        self::assertSame('x para y', (new NoteContent())->sanitize('x<p>para</p>y', false));
    }
}

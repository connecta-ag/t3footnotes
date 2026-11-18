<?php

declare(strict_types=1);

/*
 * This file is part of the t3footnotes TYPO3 extension.
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

namespace CAG\T3footnotes\Tests\Unit;

use CAG\T3footnotes\Event\CollectedFootnotesEvent;
use CAG\T3footnotes\Footnotes;
use CAG\T3footnotes\RenderOptions;
use Dom\HTMLDocument;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Http\ServerRequest;

final class FootnotesTest extends TestCase
{
    public function testChangesOnlyMarkerAndInsertionPoint(): void
    {
        $before = "<!DOCtype html>\r\n<HTML lang='en'><head><title>A &amp; B</title>"
            . '<script nonce="abc">const info = {"html":"<sup class=\\"t3foonote\\">x</sup>","entity":"&copy;"};</script></head>'
            . '<BODY data-test="x>y"><MAIN id=content><p>A&nbsp; &copy; &#x1F98A;';
        $between = '.</p><script type=application/json>{"note":"###FOOTNOTES###","html":"</main>"}</script>';
        $after = '</MAIN><!-- end --> <footer>A & B</footer></BODY></HTML>';
        $expected = $before . '<sup class="t3footnote-ref"><a id="fn-anchor-1" href="#fn-content-1" role="doc-noteref" aria-label="Footnote 1">1</a></sup>'
            . $between . '<section class="t3footnotes"><h2 class="t3footnotes__heading">Footnotes</h2><ol><li id="fn-content-1" value="1">'
            . 'Source <em>one</em> <a href="#fn-anchor-1" role="doc-backlink" aria-label="Return to reference 1">↩</a></li></ol></section>' . $after;
        self::assertSame($expected, $this->render($before . self::note('Source <em>one</em>') . $between . $after));
    }

    public function testWholeBodyIncludesImagesAndDropsBlankAndNestedMarkers(): void
    {
        $html = '<header>' . self::note('<img src="/mark.png" alt="A chart" onerror="x">') . '</header>'
            . '<main>' . self::note(' &nbsp;<em> </em><br>') . self::note('Outer ' . self::note('Inner') . ' tail') . '</main>'
            . '<footer>' . self::note('Last') . '</footer>';
        $result = $this->render($html);
        $page = $this->document($result);
        self::assertSame(3, $page->querySelectorAll('[role="doc-noteref"]')->length);
        self::assertSame('1', $page->querySelector('header sup')->textContent);
        self::assertSame('2', $page->querySelector('main sup')->textContent);
        self::assertSame('3', $page->querySelector('footer sup')->textContent);
        self::assertSame('A chart', $page->querySelector('#fn-content-1 img')->getAttribute('alt'));
        self::assertStringContainsString('Outer  tail', $page->querySelector('#fn-content-2')->textContent);
        foreach (['onerror', '[{n}]', 'Inner'] as $removed) {
            self::assertStringNotContainsString($removed, $result);
        }
    }

    public function testIgnoredElementsAndCommentsRemainByteIdentical(): void
    {
        $ignored = '<!-- ' . self::note('Comment') . ' -->';
        foreach (['script', 'style', 'template', 'noscript', 'textarea'] as $tag) {
            $ignored .= '<' . $tag . '>' . self::note($tag) . '###FOOTNOTES###</' . $tag . '>';
        }
        self::assertSame('<body>' . $ignored . '</body>', $this->render('<body>' . $ignored . '</body>'));
        $result = $this->render('<body>' . $ignored . self::note('Visible') . '</body>');
        self::assertStringContainsString($ignored, $result);
        self::assertSame(1, $this->document($result)->querySelectorAll('li')->length);
    }

    public function testLocalListsUseDistinctPrefixesAndKeepPageNumbersAndLinks(): void
    {
        $html = '<main><p id="fn-anchor-1">Claim ' . self::note('Global') . '</p>'
            . '<div data-t3footnotes-local="richtext"><div class="richtext" id="first">' . self::note('Second') . self::note('Third')
            . '</div> Outside ' . self::note('Fourth') . '<div class="richtext" id="second">' . self::note('Fifth') . '</div></div></main>';
        $result = $this->render($html);
        self::assertSame($result, $this->render($html));
        $page = $this->document($result);
        self::assertSame(3, $page->querySelectorAll('section.t3footnotes')->length);
        self::assertSame('Footnotes', $page->querySelector('#first + section h3')->textContent);
        self::assertSame('fn-content-1-note', $page->querySelector('main > section li')->id);
        self::assertSame('fn-local-1-content-2', $page->querySelector('#first + section li')->id);
        self::assertSame('fn-local-2-content-5', $page->querySelector('#second + section li')->id);

        foreach ($page->querySelectorAll('[role="doc-noteref"]') as $index => $reference) {
            $item = $page->querySelector($reference->getAttribute('href'));
            self::assertSame((string)($index + 1), $item->getAttribute('value'));
            self::assertSame('#' . $reference->id, $item->querySelector('[role="doc-backlink"]')->getAttribute('href'));
        }
    }

    #[DataProvider('placements')]
    public function testPlacement(string $template, string $selector, string $expected): void
    {
        $html = sprintf($template, self::note('Source'));
        $page = $this->document($this->render($html, $selector));
        self::assertSame('Source ↩', $page->querySelector($expected)->textContent);
        self::assertSame(1, $page->querySelectorAll('ol')->length);
        self::assertStringNotContainsString('###FOOTNOTES', $page->saveHtml());
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function placements(): iterable
    {
        yield 'main' => ['<main>%s</main><footer></footer>', 'main', 'main > section li'];
        yield 'selector' => ['<main>%s</main><footer id="sources"></footer>', 'footer#sources', '#sources > section li'];
        yield 'missing' => ['<body><article>%s</article></body>', 'main', 'body > section li'];
        yield 'invalid' => ['<body><main>%s</main></body>', '[', 'body > section li'];
        yield 'explicit' => ['<main>%s</main><footer><div data-t3footnotes-list></div></footer>', 'main', 'footer > section li'];
        yield 'standalone legacy' => ['<main>%s</main><footer>###FOOTNOTES###</footer>', 'main', 'footer > section li'];
        yield 'legacy wrapper' => ['<main>%s</main><footer>###FOOTNOTES_START###<ol id="original">###FOOTNOTES###</ol>###FOOTNOTES_END###</footer>', 'main', '#original > li'];
    }

    public function testEmptyPlacementsAreRemoved(): void
    {
        self::assertSame('<main>Claim</main>', $this->render('<main>Claim' . self::note('')
            . '<div data-t3footnotes-list></div>###FOOTNOTES_START###<ol>###FOOTNOTES###</ol>###FOOTNOTES_END###</main>'));
    }

    public function testSanitizationAndAutolinking(): void
    {
        $note = self::note('See https://example.org/path?q=A&amp;b=B. <a href="/existing">https://example.org/existing</a>'
            . '<code>https://example.org/code</code><a href="java&#x09;script:alert(1)">unsafe</a><script>bad()</script>'
            . '<em id="same" style="color: red" onclick="x">Safe</em>');
        $page = $this->document($this->render('<main>' . $note . '</main>', autoLink: true));
        self::assertSame('https://example.org/path?q=A&b=B', $page->querySelector('li a')->getAttribute('href'));
        self::assertSame(3, $page->querySelectorAll('li a[href]')->length);
        self::assertSame('Safe', $page->querySelector('li em')->textContent);
        foreach (['style=', 'onclick=', 'javascript', 'bad()', 'id="same"'] as $removed) {
            self::assertStringNotContainsString($removed, $page->saveHtml());
        }
        self::assertSame(2, $this->document($this->render('<main>' . $note . '</main>'))->querySelectorAll('li a[href]')->length);
    }

    public function testEventCanModifyAndRemoveNotesBeforeNumberingAndSanitization(): void
    {
        $request = new ServerRequest('https://example.org/');
        $events = $this->createMock(EventDispatcherInterface::class);
        $events->expects($this->once())->method('dispatch')->willReturnCallback(static function (CollectedFootnotesEvent $event) use ($request): CollectedFootnotesEvent {
            self::assertSame($request, $event->getRequest());
            self::assertSame('First', $event->getNotes()[0]->content);
            self::assertSame('Second', $event->getNotes()[1]->content);
            $event->getNotes()[0]->content = '';
            $event->getNotes()[1]->content = '<strong>Edited</strong><script>bad()</script>';
            return $event;
        });
        $result = $this->render('<main>' . self::note('First') . self::note('Second') . '</main>', request: $request, events: $events);
        $page = $this->document($result);
        self::assertSame('Edited', $page->querySelector('#fn-content-1 strong')->textContent);
        self::assertSame(1, $page->querySelectorAll('li')->length);
        self::assertStringNotContainsString('bad()', $result);
    }

    public function testReferenceMovesAfterAnEnclosingLinkWithoutLosingTheNote(): void
    {
        $html = '<main><a href="/x">Link' . self::note('<em>Source</em>') . ' continues' . self::note('Second')
            . '</a> outside</main>';
        $result = $this->render($html);
        self::assertStringContainsString('<main><a href="/x">Link continues</a><sup class="t3footnote-ref">', $result);
        $page = $this->document($result);
        self::assertSame(2, $page->querySelectorAll('main > sup a[role="doc-noteref"]')->length);
        self::assertSame('Source', $page->querySelector('#fn-content-1 em')?->textContent);
        self::assertSame('Second ↩', $page->querySelector('#fn-content-2')?->textContent);
    }

    public function testLabelTemplatesCanRepeatTheNoteNumber(): void
    {
        $options = new RenderOptions('main', 'Footnotes', 2, false, 'Ref %d, %2$d %%', 'Return %s, %d');
        $html = (new Footnotes())->render(
            '<main>' . self::note('Note') . '</main>',
            $options,
            new ServerRequest('https://example.org/'),
            self::createStub(EventDispatcherInterface::class)
        );
        $page = $this->document($html);
        self::assertSame('Ref 1, 1 %', $page->querySelector('[role="doc-noteref"]')?->getAttribute('aria-label'));
        self::assertSame('Return 1, 1', $page->querySelector('[role="doc-backlink"]')?->getAttribute('aria-label'));
    }

    public function testMalformedLegacyDelimitersAreRemovedWithoutRemovingOrdinaryContent(): void
    {
        $html = '<main>###FOOTNOTES_END###A###FOOTNOTES_START###<aside>Keep</aside>###FOOTNOTES_END###'
            . '###FOOTNOTES_START###B</main>';
        self::assertSame('<main>A<aside>Keep</aside>B</main>', $this->render($html));
        $html = '<main>' . self::note('Note') . '</main>###FOOTNOTES_START###<footer>###FOOTNOTES###</footer>';
        $page = $this->document($this->render($html));
        self::assertSame('Note ↩', $page->querySelector('footer section li')?->textContent);
        self::assertStringNotContainsString('###FOOTNOTES', $page->saveHtml());
    }

    public function testShortCommentsAndHtmlCdataDoNotHideFollowingMarkers(): void
    {
        $html = '<!doctype html><html><head><meta charset="UTF-8"><main><!-->' . self::note('One')
            . '<![CDATA[###FOOTNOTES###>' . self::note('Two') . '</main>'
            . '<svg><![CDATA[<sup class="t3foonote">Skipped</sup>###FOOTNOTES###]]></svg>'
            . '<footer>###FOOTNOTES###</footer></html>';
        $result = $this->render($html);
        self::assertStringContainsString('<!--><sup class="t3footnote-ref">', $result);
        self::assertStringContainsString('<![CDATA[###FOOTNOTES###><sup class="t3footnote-ref">', $result);
        $page = $this->document($result);
        self::assertSame(2, $page->querySelectorAll('main > sup a')->length);
        self::assertSame(2, $page->querySelectorAll('footer li')->length);
        self::assertStringNotContainsString('<footer>###FOOTNOTES###', $result);
    }

    private function render(string $html, string $selector = 'main', bool $autoLink = false, ?ServerRequest $request = null, ?EventDispatcherInterface $events = null): string
    {
        return (new Footnotes())->render(
            $html,
            new RenderOptions($selector, 'Footnotes', 2, $autoLink, 'Footnote %d', 'Return to reference %d'),
            $request ?? new ServerRequest('https://example.org/'),
            $events ?? self::createStub(EventDispatcherInterface::class)
        );
    }

    private function document(string $html): HTMLDocument
    {
        return HTMLDocument::createFromString($html, LIBXML_NOERROR, 'UTF-8');
    }

    private static function note(string $content): string
    {
        return '<sup class="t3foonote"><a class="t3foonotes-anchor" href="#fn-content-{n}" id="fn-anchor-{n}">[{n}]</a>'
            . '<span class="t3foonotes-anchor-data" style="display: none">' . $content . '</span></sup>';
    }
}

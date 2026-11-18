<?php

declare(strict_types=1);

namespace CAG\T3footnotes\Tests\Functional;

use Dom\HTMLDocument;
use PHPUnit\Framework\Attributes\TestWith;
use TYPO3\CMS\Core\Cache\Backend\Typo3DatabaseBackend;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequestContext;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class RenderFootnotesTest extends FunctionalTestCase
{
    private const NOTE = '<sup class="t3foonote"><a class="t3foonotes-anchor" href="#fn-content-{n}">[{n}]</a>'
        . '<span class="t3foonotes-anchor-data">A <em>legacy</em> source from https://example.org/archive.</span></sup>';
    private const DYNAMIC = '<sup class="t3foonote"><span class="t3foonotes-anchor-data">Dynamic note</span></sup>';

    protected array $configurationToUseInTestInstance = [
        'SYS' => ['caching' => ['cacheConfigurations' => ['pages' => ['backend' => Typo3DatabaseBackend::class]]]],
    ];
    protected array $coreExtensionsToLoad = ['fluid', 'rte_ckeditor', 'workspaces'];
    protected array $testExtensionsToLoad = ['connecta-ag/t3footnotes'];
    protected array $pathsToLinkInTestInstance = [
        'typo3conf/ext/t3footnotes/Tests/Functional/Fixtures/sites' => 'typo3conf/sites',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $pool = $this->get(ConnectionPool::class);
        foreach ([1 => 'Enabled', 2 => 'Disabled', 6 => 'Page error', 8 => 'No set'] as $uid => $title) {
            $pool->getConnectionForTable('pages')->insert('pages', [
                'uid' => $uid, 'pid' => 0, 'title' => $title, 'slug' => '/', 'doktype' => 1, 'is_siteroot' => 1,
            ]);
            $pool->getConnectionForTable('sys_template')->insert('sys_template', [
                'pid' => $uid, 'root' => 1, 'clear' => 3, 'config' => $this->typoScriptSetup(),
            ]);
        }
        $pool->getConnectionForTable('pages')->insert('pages', [
            'uid' => 3, 'pid' => 1, 'title' => 'Footnotes disabled by page condition', 'slug' => '/disabled', 'doktype' => 1,
        ]);
        $pool->getConnectionForTable('pages')->insert('pages', [
            'uid' => 4, 'pid' => 0, 'title' => 'Deutsch', 'slug' => '/', 'doktype' => 1,
            'sys_language_uid' => 1, 'l10n_parent' => 1,
        ]);
        foreach ([
            ['uid' => 5, 'pid' => 1, 'title' => 'Uncached', 'slug' => '/uncached'],
            ['uid' => 7, 'pid' => 6, 'title' => 'Page not found', 'slug' => '/error-page'],
            ['uid' => 9, 'pid' => 1, 'title' => 'Workspace preview', 'slug' => '/preview', 'subtitle' => 'Live note'],
        ] as $page) {
            $pool->getConnectionForTable('pages')->insert('pages', $page + ['doktype' => 1]);
        }
    }

    public function testColdAndWarmPageCacheContainRenderedNotesBeforeDynamicContent(): void
    {
        $request = new InternalRequest('https://footnotes.example.invalid/');
        $cold = $this->executeFrontendSubRequest($request);
        self::assertSame(200, $cold->getStatusCode());
        self::assertFalse($cold->hasHeader('X-TYPO3-Debug-Cache'));
        $coldHtml = (string)$cold->getBody();
        $page = HTMLDocument::createFromString($coldHtml, LIBXML_NOERROR, 'UTF-8');
        self::assertSame('Sources', $page->querySelector('#notes > section > h4')->textContent);
        self::assertSame('Footnote 1', $page->querySelector('[role=doc-noteref]')->getAttribute('aria-label'));
        self::assertSame('legacy', $page->querySelector('#notes li em')->textContent);
        self::assertSame('https://example.org/archive', $page->querySelector('#notes li a')->getAttribute('href'));
        self::assertSame(1, $page->querySelectorAll('[role=doc-noteref]')->length);
        self::assertStringContainsString(self::DYNAMIC, $coldHtml);

        $warm = $this->executeFrontendSubRequest($request);
        self::assertSame(200, $warm->getStatusCode());
        self::assertTrue($warm->hasHeader('X-TYPO3-Debug-Cache'));
        self::assertSame($coldHtml, (string)$warm->getBody());
    }

    public function testGermanLabelsFollowSiteLanguage(): void
    {
        $response = $this->executeFrontendSubRequest(new InternalRequest('https://footnotes.example.invalid/de/'));
        self::assertSame(200, $response->getStatusCode());
        $page = HTMLDocument::createFromString((string)$response->getBody(), LIBXML_NOERROR, 'UTF-8');
        self::assertSame('Quellenangaben', $page->querySelector('.t3footnotes__heading')->textContent);
        self::assertSame('Fußnote 1', $page->querySelector('[role=doc-noteref]')->getAttribute('aria-label'));
        self::assertSame('Zurück zu Verweis 1', $page->querySelector('[role=doc-backlink]')->getAttribute('aria-label'));
    }

    public function testSiteAndPageCanDisableRendering(): void
    {
        foreach (['https://disabled.example.invalid/', 'https://footnotes.example.invalid/disabled', 'https://no-set.example.invalid/'] as $url) {
            $response = $this->executeFrontendSubRequest(new InternalRequest($url));
            self::assertSame(200, $response->getStatusCode());
            self::assertStringContainsString(self::NOTE, (string)$response->getBody());
            self::assertStringNotContainsString('doc-noteref', (string)$response->getBody());
        }
    }

    public function testStandaloneFluidErrorHandlerBypassesPageGeneration(): void
    {
        $response = $this->executeFrontendSubRequest(new InternalRequest('https://footnotes.example.invalid/not-found'));
        self::assertSame(404, $response->getStatusCode());
        self::assertStringContainsString('<span class="t3foonotes-anchor-data">Error handler note</span>', (string)$response->getBody());
        self::assertStringNotContainsString('doc-noteref', (string)$response->getBody());
    }

    public function testTypoScriptJsonResponseIsLeftUntouched(): void
    {
        $request = (new InternalRequest('https://footnotes.example.invalid/'))->withQueryParameter('type', 91);
        $response = $this->executeFrontendSubRequest($request);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame('{"marker":"<sup class=t3foonote><span class=t3foonotes-anchor-data>Payload</span></sup>"}', (string)$response->getBody());
    }

    public function testPageBasedErrorHandlerRendersFootnotesInTheInternalSubrequest(): void
    {
        $response = $this->executeFrontendSubRequest(new InternalRequest('https://page-error.example.invalid/not-found'));
        self::assertSame(404, $response->getStatusCode());
        $page = HTMLDocument::createFromString((string)$response->getBody(), LIBXML_NOERROR, 'UTF-8');
        self::assertSame('Footnotes', $page->querySelector('.t3footnotes__heading')?->textContent);
        self::assertSame('legacy', $page->querySelector('#fn-content-1 em')?->textContent);
        self::assertSame('#fn-content-1', $page->querySelector('[role=doc-noteref]')?->getAttribute('href'));
    }

    public function testNoCacheStillRendersFootnotesOnEveryRequest(): void
    {
        $request = new InternalRequest('https://footnotes.example.invalid/uncached');
        for ($i = 0; $i < 2; $i++) {
            $response = $this->executeFrontendSubRequest($request);
            self::assertSame(200, $response->getStatusCode());
            self::assertFalse($response->hasHeader('X-TYPO3-Debug-Cache'));
            self::assertStringContainsString('role="doc-noteref"', (string)$response->getBody());
        }
    }

    public function testWorkspacePreviewRendersDraftNotesWithoutPollutingTheLiveCache(): void
    {
        $pool = $this->get(ConnectionPool::class);
        $pool->getConnectionForTable('be_users')->insert('be_users', ['uid' => 1, 'username' => 'editor', 'admin' => 1]);
        $pool->getConnectionForTable('sys_workspace')->insert('sys_workspace', ['uid' => 1, 'pid' => 0, 'title' => 'Draft']);
        $user = $this->setUpBackendUser(1);
        $user->workspace = 1;
        $handler = GeneralUtility::makeInstance(DataHandler::class);
        $handler->start(['pages' => [9 => ['subtitle' => 'Draft note']]], [], $user);
        $handler->process_datamap();
        self::assertSame([], $handler->errorLog);
        unset($GLOBALS['BE_USER']);

        $request = new InternalRequest('https://footnotes.example.invalid/preview');
        $live = $this->executeFrontendSubRequest($request);
        self::assertSame(200, $live->getStatusCode());
        self::assertStringContainsString('Live note', $this->noteText((string)$live->getBody()));
        $context = (new InternalRequestContext())->withBackendUserId(1)->withWorkspaceId(1);
        $draft = $this->executeFrontendSubRequest($request, $context);
        self::assertSame(200, $draft->getStatusCode());
        self::assertFalse($draft->hasHeader('X-TYPO3-Debug-Cache'));
        self::assertStringContainsString('Draft note', $this->noteText((string)$draft->getBody()));
        $warm = $this->executeFrontendSubRequest($request);
        self::assertTrue($warm->hasHeader('X-TYPO3-Debug-Cache'));
        self::assertStringContainsString('Live note', $this->noteText((string)$warm->getBody()));
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function testDefaultRteProcessingOfLegacyNoteOnSave(bool $sanitize): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['features']['security.backend.htmlSanitizeRte'] = $sanitize;
        $request = new ServerRequest('https://footnotes.example.invalid/');
        $GLOBALS['TYPO3_REQUEST'] = $request->withAttribute('normalizedParams', NormalizedParams::createFromRequest($request));
        $legacy = '<p><sup class="t3foonote"><a class="t3foonotes-anchor" id="fn-anchor-{n}" href="#fn-content-{n}">[{n}]</a>'
            . '<span class="t3foonotes-anchor-data" style="display: none"><em onclick="example()">Legacy</em> '
            . '<a href="https://example.org/">source</a><img src="/legacy-note.png" alt="Legacy image"></span></sup></p>';
        $pool = $this->get(ConnectionPool::class);
        $pool->getConnectionForTable('be_users')->insert('be_users', ['uid' => 1, 'username' => 'editor', 'admin' => 1]);
        $user = $this->setUpBackendUser(1);
        $connection = $pool->getConnectionForTable('tt_content');
        $connection->insert('tt_content', ['uid' => 1, 'pid' => 1, 'CType' => 'text', 'header' => 'Legacy record']);
        $handler = GeneralUtility::makeInstance(DataHandler::class);
        $handler->start(['tt_content' => [1 => ['bodytext' => $legacy]]], [], $user);
        $handler->process_datamap();
        self::assertSame([], $handler->errorLog);
        $saved = (string)$connection->select(['bodytext'], 'tt_content', ['uid' => 1])->fetchOne();
        $page = HTMLDocument::createFromString($saved, LIBXML_NOERROR, 'UTF-8');
        $note = $page->querySelector('sup.t3foonote');
        self::assertNotNull($note);
        $payload = $note->querySelector('span.t3foonotes-anchor-data');
        self::assertNotNull($payload);
        $emphasis = $payload->querySelector('em');
        $image = $payload->querySelector('img');
        self::assertNotNull($emphasis);
        self::assertNotNull($image);
        self::assertSame('Legacy', $emphasis->textContent);
        self::assertSame('https://example.org/', $payload->querySelector('a')?->getAttribute('href'));
        self::assertSame('/legacy-note.png', $image->getAttribute('src'));
        self::assertSame('Legacy image', $image->getAttribute('alt'));
        self::assertSame('display: none', $payload->getAttribute('style'));
        self::assertSame($sanitize ? null : 'example()', $emphasis->getAttribute('onclick'));
        self::assertSame('fn-anchor-{n}', $note->querySelector('a.t3foonotes-anchor')?->getAttribute('id'));
    }

    private function noteText(string $html): string
    {
        $note = HTMLDocument::createFromString($html, LIBXML_NOERROR, 'UTF-8')->querySelector('#fn-content-1');
        self::assertNotNull($note);
        return $note->textContent;
    }

    private function typoScriptSetup(): string
    {
        $note = self::NOTE;
        $dynamic = self::DYNAMIC;
        return <<<TYPOSCRIPT
            config.debug = 1
            page = PAGE
            page.10 = TEXT
            page.10.value = <header>Claim{$note}</header><main><div id="notes"></div></main>
            page.20 = COA_INT
            page.20.10 = TEXT
            page.20.10.value = {$dynamic}
            json = PAGE
            json.typeNum = 91
            json.config.disableAllHeaderCode = 1
            json.config.additionalHeaders.10.header = Content-Type: application/json
            json.10 = TEXT
            json.10.value = {"marker":"<sup class=t3foonote><span class=t3foonotes-anchor-data>Payload</span></sup>"}
            [page["uid"] == 5]
            config.no_cache = 1
            [end]
            [page["uid"] == 9]
            page.10 >
            page.10 = TEXT
            page.10.field = subtitle
            page.10.htmlSpecialChars = 1
            page.10.wrap = <main><sup class="t3foonote"><span class="t3foonotes-anchor-data">|</span></sup><div id="notes"></div></main>
            [end]
            [page["uid"] == 3]
            config.t3footnotesDisabled = 1
            [end]
            TYPOSCRIPT;
    }
}

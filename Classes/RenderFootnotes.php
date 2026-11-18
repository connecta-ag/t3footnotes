<?php

declare(strict_types=1);

/*
 * This file is part of the t3footnotes TYPO3 extension.
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

namespace CAG\T3footnotes;

use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\Set\SetRegistry;
use TYPO3\CMS\Core\TypoScript\FrontendTypoScript;
use TYPO3\CMS\Frontend\Event\AfterCacheableContentIsGeneratedEvent;
use TYPO3\CMS\Frontend\Page\PageParts;

#[AsEventListener(identifier: 't3footnotes/render')]
final readonly class RenderFootnotes
{
    private const LABELS = 'LLL:EXT:t3footnotes/Resources/Private/Language/locallang.xlf:';

    public function __construct(
        private Footnotes $footnotes,
        private LanguageServiceFactory $languages,
        private EventDispatcherInterface $events,
        private SetRegistry $sets,
    ) {}

    public function __invoke(AfterCacheableContentIsGeneratedEvent $event): void
    {
        $request = $event->getRequest();
        $site = $request->getAttribute('site');
        $language = $request->getAttribute('language');
        $parts = $request->getAttribute('frontend.page.parts');
        $typoScript = $request->getAttribute('frontend.typoscript');
        if (!$site instanceof Site || !$language instanceof SiteLanguage
            || !$parts instanceof PageParts || !$typoScript instanceof FrontendTypoScript
        ) {
            return;
        }
        $settings = $site->getSettings();
        $html = $event->getContent();
        if (!$settings->get('t3footnotes.enabled', false) || !Footnotes::hasMarkers($html)
            || !in_array('connecta-ag/t3footnotes', array_column($this->sets->getSets(...$site->getSets()), 'name'), true)
        ) {
            return;
        }
        $config = $typoScript->getConfigArray();
        if (!empty($config['t3footnotesDisabled']) || !$this->isHtml($parts, $config['additionalHeaders.'] ?? [])) {
            return;
        }
        $labels = $this->languages->createFromSiteLanguage($language);
        $heading = trim((string)$settings->get('t3footnotes.heading', ''));
        if (str_starts_with($heading, 'LLL:')) {
            $heading = $labels->sL($heading);
        }
        $event->setContent($this->footnotes->render($html, new RenderOptions(
            (string)$settings->get('t3footnotes.selector', 'main'),
            $heading === '' ? $labels->sL(self::LABELS . 'heading') : $heading,
            max(1, min(6, (int)$settings->get('t3footnotes.headingLevel', 2))),
            (bool)$settings->get('t3footnotes.autoLink', false),
            $labels->sL(self::LABELS . 'reference'),
            $labels->sL(self::LABELS . 'backlink'),
        ), $request, $this->events));
    }

    /** @param array<array-key, mixed> $additionalHeaders */
    private function isHtml(PageParts $parts, array $additionalHeaders): bool
    {
        $contentType = $parts->getHttpContentType();
        ksort($additionalHeaders);
        foreach ($additionalHeaders as $options) {
            if (is_array($options) && preg_match('/^\s*Content-Type\s*:\s*(.*)$/i', (string)($options['header'] ?? ''), $matches)) {
                $contentType = $matches[1];
            }
        }
        return strtolower(trim(explode(';', $contentType, 2)[0])) === 'text/html';
    }
}

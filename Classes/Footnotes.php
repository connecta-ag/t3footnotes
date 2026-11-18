<?php

declare(strict_types=1);

namespace CAG\T3footnotes;

use CAG\T3footnotes\Event\CollectedFootnotesEvent;
use CAG\T3footnotes\Event\Footnote;
use Dom\Element;
use Dom\HTMLDocument;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ServerRequestInterface;

/** @internal */
final class Footnotes
{
    private const MARKER = 'sup.t3foonote';
    private const IGNORED = 'script, style, template, noscript, textarea';
    private const LIST_MARKER = '[data-t3footnotes-list]';
    private const LEGACY_START = '###FOOTNOTES_START###';
    private const LEGACY_LIST = '###FOOTNOTES###';
    private const LEGACY_END = '###FOOTNOTES_END###';

    public static function hasMarkers(string $html): bool
    {
        return str_contains($html, 't3foonote') || stripos($html, 'data-t3footnotes-list') !== false || str_contains($html, '###FOOTNOTES');
    }

    public function render(string $html, RenderOptions $options, ServerRequestInterface $request, EventDispatcherInterface $events): string
    {
        if (!self::hasMarkers($html)) {
            return $html;
        }
        $source = new HtmlSource($html);
        $page = $source->document;
        $body = $page->body;
        if ($body === null) {
            return $html;
        }
        $markers = [];
        $notes = [];
        foreach ($body->querySelectorAll(self::MARKER) as $marker) {
            if ($marker->closest(self::IGNORED) !== null || $marker->parentElement?->closest(self::MARKER) !== null) {
                continue;
            }
            $range = $source->range($marker);
            if ($range === null || isset($markers[$range[0]])) {
                continue;
            }
            $markers[$range[0]] = [$marker, $range];
            $notes[$range[0]] = new Footnote($this->payload($source, $marker, $range));
        }
        if ($notes !== []) {
            $events->dispatch(new CollectedFootnotesEvent($request, array_values($notes)));
        }

        $content = new NoteContent();
        $number = 0;
        $edits = [];
        $items = [];
        /** @var \SplObjectStorage<Element, int> $localLists */
        $localLists = new \SplObjectStorage();
        foreach ($markers as $key => [$marker, [$start, $end]]) {
            $text = $content->sanitize($notes[$key]->content, $options->autoLink);
            if ($text === '') {
                $edits[] = [$start, $end, ''];
                continue;
            }
            $local = $this->localBlock($marker);
            $localEnd = $local === null ? null : ($source->range($local)[1] ?? null);
            $prefix = 'fn-';
            if ($local !== null && $localEnd !== null) {
                if (!isset($localLists[$local])) {
                    $localLists[$local] = count($localLists) + 1;
                }
                $prefix .= 'local-' . $localLists[$local] . '-';
            }

            $number++;
            [$anchorId, $contentId] = $this->ids($page, $prefix, $number);
            $reference = '<sup class="t3footnote-ref"><a id="' . $anchorId . '" href="#' . $contentId . '" role="doc-noteref" aria-label="'
                . self::label($options->referenceLabel, $number) . '">' . $number . '</a></sup>';
            $backlink = '<a href="#' . $anchorId . '" role="doc-backlink" aria-label="'
                . self::label($options->backlinkLabel, $number) . '">↩</a>';
            $items[$localEnd ?? -1][] = '<li id="' . $contentId . '" value="' . $number . '">' . $text . ' ' . $backlink . '</li>';

            // Keep anchors valid if a theme emitted a marker within an existing link.
            $linkEnd = $source->enclosingLinkEnd([$start, $end]);
            if ($linkEnd !== null) {
                $edits[] = [$start, $end, ''];
                $edits[] = [$linkEnd, $linkEnd, $reference];
            } else {
                $edits[] = [$start, $end, $reference];
            }
        }

        $pageItems = implode('', $items[-1] ?? []);
        unset($items[-1]);
        foreach ($items as $after => $localItems) {
            $edits[] = [$after, $after, $this->section(implode('', $localItems), $options, true)];
        }
        $this->placePageList($source, $body, $pageItems, $options, $edits);
        return $source->apply($edits);
    }

    /** @param array{int, int} $range */
    private function payload(HtmlSource $source, Element $marker, array $range): string
    {
        for ($child = $marker->firstElementChild; $child !== null; $child = $child->nextElementSibling) {
            if ($child->matches('span.t3foonotes-anchor-data')) {
                return $source->innerHtml($child);
            }
        }
        if ($source->enclosingLinkEnd($range) === null) {
            return '';
        }
        // HTML repairs nested links before exposing the DOM. Parse the marker's
        // original range on its own so the payload remains a child of the marker.
        $isolated = new HtmlSource($source->sourceBetween($range));
        $payload = $isolated->document->querySelector('sup.t3foonote > span.t3foonotes-anchor-data');
        return $payload === null ? '' : $isolated->innerHtml($payload);
    }

    /** @return array{string, string} */
    private function ids(HTMLDocument $page, string $prefix, int $number): array
    {
        $suffix = (string)$number;
        while ($page->getElementById($prefix . 'anchor-' . $suffix) || $page->getElementById($prefix . 'content-' . $suffix)) {
            $suffix .= '-note';
        }
        return [$prefix . 'anchor-' . $suffix, $prefix . 'content-' . $suffix];
    }

    private function localBlock(Element $marker): ?Element
    {
        $scope = $marker->closest('[data-t3footnotes-local]');
        if ($scope?->getAttribute('data-t3footnotes-local') !== 'richtext') {
            return null;
        }
        $block = $marker->parentElement?->closest('.richtext');
        return $block !== null && $block !== $scope && $scope->contains($block) ? $block : null;
    }

    /** @param list<array{int, int, string}> $edits */
    private function placePageList(HtmlSource $source, Element $body, string $items, RenderOptions $options, array &$edits): void
    {
        $section = $items === '' ? '' : $this->section($items, $options);
        $placements = [];
        foreach ($body->querySelectorAll(self::LIST_MARKER) as $marker) {
            if ($marker->closest(self::IGNORED . ', ' . self::MARKER) === null && ($range = $source->range($marker)) !== null) {
                $placements[] = [$range[0], $range[1], null];
            }
        }
        $group = null;
        $discard = [];
        foreach ($source->legacyTokens as [$start, $end, $token]) {
            if ($this->overlapsEdits($start, $end, $edits)) {
                continue;
            }
            if ($token === self::LEGACY_START) {
                if ($group !== null) {
                    $discard[] = [$group[0], $group[1], ''];
                    if ($group[2] !== null) {
                        $placements[] = [...$group[2], null];
                    }
                }
                $group = [$start, $end, null];
            } elseif ($token === self::LEGACY_LIST) {
                if ($group === null) {
                    $placements[] = [$start, $end, null];
                } elseif ($group[2] === null) {
                    $group[2] = [$start, $end];
                } else {
                    $discard[] = [$start, $end, ''];
                }
            } elseif ($token === self::LEGACY_END) {
                if ($group !== null && $group[2] !== null
                    && !$this->overlapsEdits($group[0], $end, $edits)
                ) {
                    $placements[] = [$group[0], $end, [$group[1], $start, ...$group[2]]];
                } else {
                    $discard[] = [$start, $end, ''];
                    if ($group !== null) {
                        $discard[] = [$group[0], $group[1], ''];
                        if ($group[2] !== null) {
                            $placements[] = [...$group[2], null];
                        }
                    }
                }
                $group = null;
            }
        }
        if ($group !== null) {
            $discard[] = [$group[0], $group[1], ''];
            if ($group[2] !== null) {
                $placements[] = [...$group[2], null];
            }
        }
        usort($placements, static fn(array $left, array $right): int => $left[0] <=> $right[0]);
        $placed = false;
        $previousEnd = -1;
        foreach ($placements as [$start, $end, $legacy]) {
            if ($start < $previousEnd || $this->overlapsEdits($start, $end, $edits)) {
                continue;
            }
            $previousEnd = $end;
            if ($legacy === null || $placed || $items === '') {
                $edits[] = [$start, $end, $placed ? '' : $section];
            } else {
                [$startEnd, $endStart, $listStart, $listEnd] = $legacy;
                $edits[] = [$start, $startEnd, ''];
                $edits[] = [$listStart, $listEnd, $items];
                $edits[] = [$endStart, $end, ''];
            }
            $placed = true;
        }

        array_push($edits, ...$discard);

        if (!$placed && $items !== '') {
            $container = null;
            if ($options->selector !== '') {
                try {
                    $container = $body->matches($options->selector) ? $body : $body->querySelector($options->selector);
                } catch (\DOMException) {
                    // Invalid CSS selector: fall back to the body.
                }
            }
            $offset = $container !== null && $container->closest(self::IGNORED . ', ' . self::MARKER) === null
                ? $source->beforeClosingTag($container) : null;
            $offset ??= $source->beforeClosingTag($body);
            if ($offset !== null) {
                $edits[] = [$offset, $offset, $section];
            }
        }
    }

    /** @param list<array{int, int, string}> $edits */
    private function overlapsEdits(int $start, int $end, array $edits): bool
    {
        foreach ($edits as [$editStart, $editEnd]) {
            if ($editStart < $end && $editEnd > $start) {
                return true;
            }
        }
        return false;
    }

    private function section(string $items, RenderOptions $options, bool $local = false): string
    {
        $level = $local ? min(6, $options->level + 1) : $options->level;
        $class = $local ? 't3footnotes t3footnotes--local' : 't3footnotes';
        return '<section class="' . $class . '"><h' . $level . ' class="t3footnotes__heading">' . self::escape($options->heading)
            . '</h' . $level . '><ol>' . $items . '</ol></section>';
    }

    private static function label(string $label, int $number): string
    {
        // A translator can repeat the note number or use a positional placeholder.
        return self::escape((string)preg_replace_callback('/%%|%(?:[1-9][0-9]*\$)?[ds]/', static fn(array $match): string => $match[0] === '%%' ? '%' : (string)$number, $label));
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }
}

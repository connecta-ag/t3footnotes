<?php

declare(strict_types=1);

/*
 * This file is part of the t3footnotes TYPO3 extension.
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

namespace CAG\T3footnotes;

use Dom\Element;
use Dom\HTMLDocument;

/**
 * PHP's HTML DOM has no byte offsets. Parse a scratch copy with source offsets
 * on opening tags, then apply edits to the untouched input.
 *
 * @internal
 */
final class HtmlSource
{
    private const TAG = <<<'REGEX'
~<!--(?:-?>|.*?(?:--!?>|\z))|<!\[CDATA\[|<![^>]*>|<\?[^>]*>|<(/?)([a-z][a-z0-9:._-]*)(?=[\s/>])(?:[^"'<>]|"[^"]*"|'[^']*')*>~is
REGEX;
    private const RAW_TEXT = ['script', 'style', 'noscript', 'textarea', 'title', 'xmp', 'iframe', 'noembed', 'noframes', 'plaintext'];

    public readonly HTMLDocument $document;

    private readonly string $offsetAttribute;

    /** @var array<int, array{int, int}> Opening offset to closing tag range. */
    private array $ends = [];

    /** @var list<array{int, int, string}> */
    public private(set) array $legacyTokens = [];

    /** @var array<int, int> Opening offset to the first byte after the tag. */
    private array $contentStarts = [];

    /** @var array<int, int> Superscript opening offsets to enclosing link opening offsets. */
    private array $enclosingLinks = [];

    private ?int $bodyEnd = null;

    public function __construct(private readonly string $html)
    {
        $attribute = 'data-t3footnotes-offset';
        while (stripos($html, $attribute) !== false) {
            $attribute .= '-x';
        }
        $this->offsetAttribute = $attribute;

        $annotated = '';
        $copied = 0;
        $cursor = 0;
        $open = [];
        $raw = null;
        while ($cursor < strlen($html)) {
            $pattern = $raw === null ? self::TAG : '~<(/)(' . $raw . ')(?=[\s/>])[^>]*>~i';
            if ($raw === 'plaintext' || !preg_match($pattern, $html, $matches, PREG_OFFSET_CAPTURE, $cursor)) {
                if ($raw === null && empty($open['template'])) {
                    $this->readLegacyTokens($cursor, strlen($html));
                }
                break;
            }
            [$tag, $start] = $matches[0];
            if ($raw === null && empty($open['template'])) {
                $this->readLegacyTokens($cursor, $start);
            }
            $cursor = $start + strlen($tag);
            if (strncasecmp($tag, '<![CDATA[', 9) === 0) {
                // HTML treats this as a bogus comment. Only foreign content permits CDATA.
                $foreign = $tag === '<![CDATA[' && (!empty($open['svg']) || !empty($open['math']));
                $terminator = $foreign ? ']]>' : '>';
                $end = strpos($html, $terminator, $cursor);
                $cursor = $end === false ? strlen($html) : $end + strlen($terminator);
            }
            if (!isset($matches[2])) {
                continue;
            }
            $name = strtolower($matches[2][0]);
            if ($matches[1][0] === '/') {
                if (!empty($open[$name])) {
                    $this->ends[array_pop($open[$name])] = [$start, $cursor];
                }
                if ($name === 'body' || $name === 'html') {
                    $this->bodyEnd ??= $start;
                }
                $raw = null;
                continue;
            }
            // End tags are only needed for elements with an explicit closing
            // tag. Void tags have no range, so cannot act as an insertion point.
            if ($name === 'sup' && !empty($open['a'])) {
                $this->enclosingLinks[$start] = $open['a'][array_key_last($open['a'])];
            }
            $open[$name][] = $start;
            $this->contentStarts[$start] = $cursor;
            $position = $start + 1 + strlen($matches[2][0]);
            $annotated .= substr($html, $copied, $position - $copied) . ' ' . $attribute . '="' . $start . '"';
            $copied = $position;
            if (in_array($name, self::RAW_TEXT, true)) {
                $raw = $name;
            }
        }

        usort($this->legacyTokens, static fn(array $left, array $right): int => $left[0] <=> $right[0]);
        $this->document = HTMLDocument::createFromString($annotated . substr($html, $copied), LIBXML_NOERROR, 'UTF-8');
    }

    /** @return array{int, int}|null */
    public function range(Element $element): ?array
    {
        $offset = $element->getAttribute($this->offsetAttribute);
        $closing = $offset === null ? null : ($this->ends[(int)$offset] ?? null);
        return $closing === null ? null : [(int)$offset, $closing[1]];
    }

    public function innerHtml(Element $element): string
    {
        $offset = $element->getAttribute($this->offsetAttribute);
        if ($offset !== null && isset($this->ends[(int)$offset])) {
            $start = $this->contentStarts[(int)$offset];
            return substr($this->html, $start, $this->ends[(int)$offset][0] - $start);
        }
        return '';
    }

    /** @param array{int, int} $range */
    public function enclosingLinkEnd(array $range): ?int
    {
        $link = $this->enclosingLinks[$range[0]] ?? null;
        $end = $link === null ? null : ($this->ends[$link][1] ?? null);
        return $end !== null && $end > $range[1] ? $end : null;
    }

    /** @param array{int, int} $range */
    public function sourceBetween(array $range): string
    {
        return substr($this->html, $range[0], $range[1] - $range[0]);
    }

    public function beforeClosingTag(Element $element): ?int
    {
        $offset = $element->getAttribute($this->offsetAttribute);
        return ($offset === null ? null : ($this->ends[(int)$offset][0] ?? null))
            ?? ($element->localName === 'body' ? ($this->bodyEnd ?? strlen($this->html)) : null);
    }

    /** @param list<array{int, int, string}> $edits Byte ranges, in application order for insertions at the same offset. */
    public function apply(array $edits): string
    {
        usort($edits, static fn(array $left, array $right): int => [$left[0], $left[1]] <=> [$right[0], $right[1]]);
        $cursor = 0;
        $result = '';
        foreach ($edits as [$start, $end, $replacement]) {
            if ($start < $cursor) {
                continue;
            }
            $result .= substr($this->html, $cursor, $start - $cursor) . $replacement;
            $cursor = $end;
        }
        return $result . substr($this->html, $cursor);
    }

    private function readLegacyTokens(int $start, int $end): void
    {
        if ($end > $start && preg_match_all('/###FOOTNOTES(?:_START|_END)?###/', substr($this->html, $start, $end - $start), $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as [$token, $offset]) {
                $this->legacyTokens[] = [$start + $offset, $start + $offset + strlen($token), $token];
            }
        }
    }
}

<?php

declare(strict_types=1);

namespace CAG\T3footnotes;

use Dom\Element;
use Dom\HTMLDocument;
use Dom\Node;
use Dom\Text;

/** @internal */
final class NoteContent
{
    private const INLINE = [
        'a', 'abbr', 'b', 'bdi', 'bdo', 'br', 'cite', 'code', 'del', 'dfn', 'em', 'i', 'img', 'ins',
        'kbd', 'mark', 'q', 'ruby', 'rp', 'rt', 's', 'samp', 'small', 'span', 'strong', 'sub',
        'sup', 'time', 'u', 'var', 'wbr',
    ];
    private const BLOCK = [
        'address', 'article', 'aside', 'blockquote', 'caption', 'dd', 'details', 'div', 'dl', 'dt',
        'fieldset', 'figcaption', 'figure', 'footer', 'form', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'header', 'hr', 'li', 'main', 'menu', 'nav', 'ol', 'p', 'pre', 'section', 'summary', 'table',
        'tbody', 'td', 'tfoot', 'th', 'thead', 'tr', 'ul',
    ];
    private const DISCARD = 'script, style, template, noscript, textarea, iframe, object, svg, math, sup.t3foonote';

    public function sanitize(string $html, bool $autoLink): string
    {
        $input = HTMLDocument::createFromString('<body>' . $html . '</body>', LIBXML_NOERROR, 'UTF-8');
        $output = HTMLDocument::createEmpty('UTF-8');
        $fragment = $output->createDocumentFragment();
        $hasContent = false;
        foreach ($input->body->childNodes as $node) {
            $hasContent = $this->append($fragment, $node, $autoLink) || $hasContent;
        }
        return $hasContent ? $output->saveHtml($fragment) : '';
    }

    private function append(Node $parent, Node $node, bool $autoLink): bool
    {
        $document = $parent->ownerDocument;
        if ($node instanceof Text) {
            if ($autoLink && preg_match_all('~https?://[^\s<>"\']+~iu', $node->data, $matches, PREG_OFFSET_CAPTURE)) {
                $offset = 0;
                foreach ($matches[0] as [$match, $start]) {
                    $candidate = rtrim($match, '.,;:!?)]}');
                    $url = $this->safeUrl($candidate, false);
                    if ($url === null || !parse_url($url, PHP_URL_HOST)) {
                        continue;
                    }
                    $parent->appendChild($document->createTextNode(substr($node->data, $offset, $start - $offset)));
                    $link = $document->createElement('a');
                    $link->setAttribute('href', $url);
                    $link->textContent = $url;
                    $parent->appendChild($link);
                    $offset = $start + strlen($candidate);
                }
                $parent->appendChild($document->createTextNode(substr($node->data, $offset)));
            } else {
                $parent->appendChild($document->createTextNode($node->data));
            }
            return preg_match('/[^\s\p{Z}\x{200B}\x{FEFF}]/u', $node->data) === 1;
        }
        if (!$node instanceof Element || $node->matches(self::DISCARD)) {
            return false;
        }

        $name = $node->localName;
        $destination = $parent;
        if (in_array($name, self::INLINE, true)) {
            $destination = $document->createElement($name);
            $attributes = ['title', 'lang', 'dir'];
            if ($name === 'a') {
                $attributes[] = 'href';
            } elseif ($name === 'img') {
                array_push($attributes, 'src', 'alt', 'width', 'height');
            }
            foreach ($attributes as $attribute) {
                $value = $node->getAttribute($attribute);
                if ($value !== null && in_array($attribute, ['href', 'src'], true)) {
                    $value = $this->safeUrl($value, $name === 'a');
                }
                if ($value !== null) {
                    $destination->setAttribute($attribute, $value);
                }
            }
            if ($name === 'img' && !$destination->hasAttribute('src')) {
                return false;
            }
            $parent->appendChild($destination);
        }

        $block = in_array($name, self::BLOCK, true);
        if ($block) {
            $parent->appendChild($document->createTextNode(' '));
        }
        $hasContent = $name === 'img';
        foreach ($node->childNodes as $child) {
            $hasContent = $this->append($destination, $child, $autoLink && $name !== 'a' && $name !== 'code') || $hasContent;
        }
        if ($block) {
            $parent->appendChild($document->createTextNode(' '));
        }
        return $hasContent;
    }

    private function safeUrl(string $value, bool $isLink): ?string
    {
        // Follow the browser's URL preprocessing before identifying a scheme.
        $value = str_replace(["\t", "\n", "\r"], '', $value);
        $value = (string)preg_replace('/^[\x00-\x20]+|[\x00-\x20]+$/', '', $value);
        if ($value === '' || preg_match('/[\x00-\x20\x7f]/', $value)) {
            return null;
        }
        if (preg_match('/^([a-z][a-z0-9+.-]*):/i', $value, $matches)) {
            $schemes = $isLink ? ['http', 'https', 'mailto', 'tel'] : ['http', 'https'];
            return in_array(strtolower($matches[1]), $schemes, true) ? $value : null;
        }
        $colon = strpos($value, ':');
        return $colon === false || $colon > strcspn($value, '/?#') ? $value : null;
    }
}

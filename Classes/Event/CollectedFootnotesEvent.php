<?php

declare(strict_types=1);

namespace CAG\T3footnotes\Event;

use Psr\Http\Message\ServerRequestInterface;

/** Modify or remove a collected note before numbering and cache storage. */
final readonly class CollectedFootnotesEvent
{
    /** @param list<Footnote> $notes In document reading order. */
    public function __construct(private ServerRequestInterface $request, private array $notes) {}

    public function getRequest(): ServerRequestInterface
    {
        return $this->request;
    }

    /** @return list<Footnote> */
    public function getNotes(): array
    {
        return $this->notes;
    }
}

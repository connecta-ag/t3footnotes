<?php

declare(strict_types=1);

namespace CAG\T3footnotes;

/** @internal */
final readonly class RenderOptions
{
    public function __construct(
        public string $selector,
        public string $heading,
        public int $level,
        public bool $autoLink,
        public string $referenceLabel,
        public string $backlinkLabel,
    ) {}
}

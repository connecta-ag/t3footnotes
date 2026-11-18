<?php

declare(strict_types=1);

/*
 * This file is part of the t3footnotes TYPO3 extension.
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

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

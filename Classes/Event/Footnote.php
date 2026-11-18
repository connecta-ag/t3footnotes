<?php

declare(strict_types=1);

/*
 * This file is part of the t3footnotes TYPO3 extension.
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

namespace CAG\T3footnotes\Event;

/**
 * Note content as inline HTML. The renderer sanitizes edits from listeners.
 * Set content to an empty string to remove the note and its reference.
 */
final class Footnote
{
    public function __construct(public string $content) {}
}

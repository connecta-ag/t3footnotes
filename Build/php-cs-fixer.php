<?php

declare(strict_types=1);

/*
 * This file is part of the t3footnotes TYPO3 extension.
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

use TYPO3\CodingStandards\CsFixerConfig;

$config = CsFixerConfig::create()
    ->setUsingCache(false)
    ->setHeader(
        'This file is part of the t3footnotes TYPO3 extension.' . "\n\n" . 'SPDX-License-Identifier: GPL-2.0-or-later',
        true,
    );
$config->getFinder()->in([__DIR__, __DIR__ . '/../Classes', __DIR__ . '/../Configuration', __DIR__ . '/../Tests']);

return $config;

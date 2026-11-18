<?php

declare(strict_types=1);

use TYPO3\CodingStandards\CsFixerConfig;

$config = CsFixerConfig::create()->setUsingCache(false);
$config->getFinder()->in([__DIR__, __DIR__ . '/../Classes', __DIR__ . '/../Configuration', __DIR__ . '/../Tests']);

return $config;

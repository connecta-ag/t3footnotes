<?php defined('TYPO3_MODE') || defined('TYPO3') || die();

\TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerPlugin(
  'CAG.T3footnotes',
  'List',
  'Footnotes'
);

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile(
    't3footnotes',
    'Configuration/TypoScript',
    'T3footnotes Plugin'
);

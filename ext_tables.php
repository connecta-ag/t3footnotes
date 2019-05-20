<?php
defined('TYPO3_MODE') or die();

// 2019-05-16 CAG JR TODO: check if this file is of any use at all; if not, let's kick it out again one day

// Register Plugin and namespaces
//\TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerPlugin(
//    'CAG.' . $_EXTKEY,
//    'CKEditor',
//    'CKEditor'
//);
//// Add Plugin Configs
//\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile($_EXTKEY, 'Configuration/TypoScript', 'CKEditor');


/**
 * Page TS Config
 */
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPageTSConfig('<INCLUDE_TYPOSCRIPT: source="FILE:EXT:t3footnotes/Configuration/TsConfig/rte.tsconfig">');

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTypoScript('t3footnotes', 'setup','<INCLUDE_TYPOSCRIPT: source="FILE:EXT:t3footnotes/Configuration/TypoScript/rte.ts">');

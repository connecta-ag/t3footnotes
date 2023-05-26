<?php defined('TYPO3_MODE') || defined('TYPO3') || die();

declare(strict_types=1);

\TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
    'T3footnotes',
    'Footnotes',
    [\CAG\T3footnotes\Controller\FootnoteController::class => 'list,show'],
    []
);

\TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Imaging\IconRegistry::class)->registerIcon(
    't3footnotes-plugin-list',
    \TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider::class,
    ['source' => 'EXT:t3footnotes/Resources/Public/Icons/user_plugin_list.svg']
);

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_fe.php']['contentPostProc-all']['t3footnotes'] =
    \CAG\T3footnotes\Hooks\FootnotesHook::class . '->generateFootnotes';

<?php
defined('TYPO3_MODE') or die();

call_user_func(
    function()
    {

        \TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerPlugin(
            'CAG.T3footnotes',
            'List',
            'Footnotes'
        );

        /**
         * Add contants and typoscript
         */
        \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTypoScript(
            't3footnotes',
            'constants',
            '<INCLUDE_TYPOSCRIPT: source="FILE:EXT:t3footnotes/Configuration/TypoScript/constants.cts">'
        );
        \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTypoScript(
            't3footnotes',
            'setup',
            '<INCLUDE_TYPOSCRIPT: source="FILE:EXT:t3footnotes/Configuration/TypoScript/setup.ts">'
        );


    }
);


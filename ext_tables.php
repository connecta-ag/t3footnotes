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
        \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile('t3footnotes', 'Configuration/TypoScript', 'T3footnotes Plugin');


    }
);


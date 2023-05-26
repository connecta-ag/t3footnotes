<?php defined('TYPO3') || die();

call_user_func(
    function()
    {

        \TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerPlugin(
            'CAG.T3footnotes',
            'List',
            'Footnotes'
            [
                CAG\T3footnotes\Controller\FootnoteController::class => 'list,show'
            ],
            []
        );

        /**
         * Add contants and typoscript
         */
        \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile('t3footnotes', 'Configuration/TypoScript', 'T3footnotes Plugin');

    }
);

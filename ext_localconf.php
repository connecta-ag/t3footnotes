<?php defined('TYPO3') || die();

call_user_func(
    function()
    {

        \TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
            'CAG.T3footnotes',
            'List',
            [
                CAG\T3footnotes\Controller\FootnoteController::class => 'list, show'
            ],

            [
                // non-cacheable actions
            ]
        );

        // Not needed. Use of the plugin is via lib_object
        //
        //        // wizards
        //        \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPageTSConfig(
        //            'mod {
        //            wizards.newContentElement.wizardItems.plugins {
        //                elements {
        //                    list {
        //                        iconIdentifier = t3footnotes-plugin-list
        //                        title = LLL:EXT:t3footnotes/Resources/Private/Language/locallang_db.xlf:tx_t3footnotes_list.name
        //                        description = LLL:EXT:t3footnotes/Resources/Private/Language/locallang_db.xlf:tx_t3footnotes_list.description
        //                        tt_content_defValues {
        //                            CType = list
        //                            list_type = t3footnotes_list
        //                        }
        //                    }
        //                }
        //                show = *
        //            }
        //       }'
        //        );

        $iconRegistry = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Imaging\IconRegistry::class);

        $iconRegistry->registerIcon(
            't3footnotes-plugin-list',
            \TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider::class,
            ['source' => 'EXT:t3footnotes/Resources/Public/Icons/user_plugin_list.svg']
        );


        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_fe.php']['contentPostProc-all']['t3footnotes'] =
            \CAG\T3footnotes\Hooks\FootnotesHook::class . '->generateFootnotes';

    }
);

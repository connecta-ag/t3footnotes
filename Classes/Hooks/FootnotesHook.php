<?php
namespace CAG\T3footnotes\Hooks;


use TYPO3\CMS\Core\TypoScript\TypoScriptService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManager;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Fluid\View\StandaloneView;

/***
 *
 * This file is part of the "Fuss" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2019
 *
 ***/
/**
 * FootnoteController
 */
class FootnotesHook
{

    const MARKER_FOOTNOTES = '###FOOTNOTES###';
    const MARKER_FOOTNOTES_START = '###FOOTNOTES_START###';
    const MARKER_FOOTNOTES_END = '###FOOTNOTES_END###';
    const MARKER_FOOTNOTE_ANCHOR_NR = '{n}';


    /**
     * @var ConfigurationManager
     */
    protected $configurationManager;

    /**
     * @var ObjectManager
     */
    protected $objectManager;

    protected $fonfig = null;


    public function __construct()
    {
        $this->configurationManager = GeneralUtility::makeInstance(ConfigurationManager::class);

        $this->objectManager = GeneralUtility::makeInstance(ObjectManager::class);

        /** @var  TypoScriptService $tsService */
        $tsService = GeneralUtility::makeInstance(TypoScriptService::class);
        $fullTs = $this->configurationManager->getConfiguration($this->configurationManager::CONFIGURATION_TYPE_FULL_TYPOSCRIPT);

        $this->config = $tsService->convertTypoScriptArrayToPlainArray($fullTs['plugin.']['tx_t3footnotes.']);
    }

    /**
     * @param array $params
     * @param \TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController $pObj
     */
    public function generateFootnotes($params, $pObj)
    {
        // init vars
        $patternFootnoteAnchors = '/<sup[ ]+class="t3foonote">(?:.(?!\<\/sup\>))*.<\/sup>/i';
        $patternFootnoteAnchorDataAttr = '/(<span[ ]+class="t3foonotes-anchor-data".*?>)((?:.(?!\<\/span\>))*.)(<\/span>)/i';
        $patternFootnoteAnchorLink = '/(<a.*?class="t3foonotes-anchor".*?>)((?:.(?!\<\/a\>))*.)(<\/a>)/i';

        $hrefPattern = '/(href=".*?")/i';

        $tempMarkerAnchor = '#########SUP#########';
        $patterntempMarkerAnchor = '/' . $tempMarkerAnchor . '/';
        $matchesFootnoteData = [];
        $footnotes = [];

        $footnoteAnchors = $this->getFootnotesAnchors($patternFootnoteAnchors, $pObj);

        $currentUrl = $pObj->cObj->getUrlToCurrentLocation(true);


        // if found footnotes anchors process build footnotes
        if ($footnoteAnchors) {

            // replace all anchors by temp marker
            $pObj->content = preg_replace($patternFootnoteAnchors, $tempMarkerAnchor, $pObj->content);


            $nr = 1;
            $limitTempMarkerAnchor = 1;

            foreach ($footnoteAnchors as $index => $footnoteAnchor) {

                // get footnote text and remove data span
                preg_match($patternFootnoteAnchorDataAttr, $footnoteAnchor, $matchesFootnoteData);
                preg_match($patternFootnoteAnchorLink, $footnoteAnchor, $matchesFootnoteLink);

                // build only valid anchors with anchol link and content
                if (sizeof($matchesFootnoteData) == 4 && sizeof($matchesFootnoteLink) == 4) {
                    $footnoteContent = $matchesFootnoteData[2];

                        $footnotes[] = ['data' => $footnoteContent, 'nr' => $nr];
                        $footnoteAnchor = preg_replace($patternFootnoteAnchorDataAttr, '', $footnoteAnchor);

                        $hrefToReplace = "href=\"" . $currentUrl . '#fn-content-' . self::MARKER_FOOTNOTE_ANCHOR_NR . "\"";

                        // set right anchor link
                        $footnoteAnchor = preg_replace($hrefPattern, $hrefToReplace, $footnoteAnchor);


                        // set anchor numbers
                        $footnoteAnchor = str_replace(
                            [self::MARKER_FOOTNOTE_ANCHOR_NR, urlencode(self::MARKER_FOOTNOTE_ANCHOR_NR)],
                            $nr,
                            $footnoteAnchor
                        );

                        $nr++;

                } else {
                    $footnotes[] = ['data' => '', 'nr' => 0];
                    $footnoteAnchor = '';
                }

                // replace first (current) temp anchor marker in content by modified footnote anchor
                $pObj->content = preg_replace ( $patterntempMarkerAnchor, $footnoteAnchor, $pObj->content, $limitTempMarkerAnchor);



            }
        }



        $containerFootnotes = $this->buildFootnotesContainer($footnotes, $pObj);

        // add footnotes container with generatet footnotes to page content
        $patternReplaceContainer = '/' . self::MARKER_FOOTNOTES_START . '[\w\W]*(?=' . self::MARKER_FOOTNOTES_END . ')' . self::MARKER_FOOTNOTES_END . '/';
        $pObj->content = preg_replace ( $patternReplaceContainer, $containerFootnotes, $pObj->content);
    }

    /**
     * @param $patternFootnoteAnchors
     * @param \TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController $pObj
     * @return array
     */
    protected function getFootnotesAnchors($patternFootnoteAnchors, $pObj)
    {
        $footnoteAnchors = [];

        preg_match_all($patternFootnoteAnchors, $pObj->content, $matchesFootnotesAnchors, PREG_PATTERN_ORDER);
        // if found footnotes anchors
        if (isset($matchesFootnotesAnchors[0]) && sizeof($matchesFootnotesAnchors[0])) {
            $footnoteAnchors = $matchesFootnotesAnchors[0];
        }

        return $footnoteAnchors;
    }

    /**
     * @param array $footnotes prepared footnotes date with anchor number
     * @param \TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController $pObj
     * @return string
     */
    protected function buildFootnotesContainer($footnotes, $pObj)
    {
        $containerFootnotes = '';

        if (sizeof($footnotes)) {
            $patternContainer = '/(' . self::MARKER_FOOTNOTES_START . ')([\w\W]*)(?=' . self::MARKER_FOOTNOTES_END . ')(' . self::MARKER_FOOTNOTES_END . ')/';
            preg_match($patternContainer, $pObj->content, $matches_container);
            if(sizeof($matches_container) == 4) {

                $containerFootnotes = $matches_container[2];

                $footnotesHtml = '';

                foreach ($footnotes as $footnote) {
                    if ($footnote['nr'] != 0) {
                        $footnotesHtml .= $this->buildFootnoteItem($footnote);
                    }
                }

                $containerFootnotes = str_replace(self::MARKER_FOOTNOTES, $footnotesHtml, $containerFootnotes);
            }
        }

        return $containerFootnotes;
    }


    /**
     * @param $footnote
     */
    protected function buildFootnoteItem($footnote)
    {

        /** @var StandaloneView $tplView */
        $tplView = $this->objectManager->get(StandaloneView::class);

        $tplView->setTemplateRootPaths($this->config['view']['templateRootPaths']);
        $tplView->setPartialRootPaths($this->config['view']['partialRootPaths']);
        $tplView->setLayoutRootPaths($this->config['view']['layoutRootPaths']);
        $tplView->setTemplate('Footnote/Item');

        $tplView->assign('footnote', $footnote);
        $footnoteHtml = $tplView->render();

        return $footnoteHtml;
    }


}

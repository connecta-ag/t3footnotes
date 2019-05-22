<?php
namespace CAG\T3footnotes\Hooks;



use FelixNagel\T3extblog\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\DebuggerUtility;

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
class FootnotesHook extends \TYPO3\CMS\Extbase\Mvc\Controller\ActionController
{

    const MARKER_FOOTNOTES = '###FOOTNOTES###';
    const MARKER_FOOTNOTES_START = '###FOOTNOTES_START###';
    const MARKER_FOOTNOTES_END = '###FOOTNOTES_END###';
    const MARKER_FOOTNOTE_ANCHOR_NR = '{n}';

    /**
     * @param array $params
     * @param \TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController $pObj
     */
    public function generateFootnotes($params, $pObj)
    {

        $containerFootnotes = '';
        $patternFootnoteAnchors = '/<sup[ ]+class="t3foonote">(?:.(?!\<\/sup\>))*.<\/sup>/i';
        $patternDataAttrFootnoteAnchor = '/(<span[ ]+class="t3foonotes-anchor-data".*>)((?:.(?!\<\/span\>))*.)(<\/span>)/i';
        $matchesFootnotes = [];
        $matchesFootnoteData = [];
        $footnotes = [];
        $footnoteAnchors = [];

        preg_match_all($patternFootnoteAnchors, $pObj->content, $matchesFootnotes, PREG_PATTERN_ORDER);


        // if found footnotes anchors
        if (isset($matchesFootnotes[0]) && sizeof($matchesFootnotes[0])) {

            $footnoteAnchors = $matchesFootnotes[0];


            // replace all anchors by temp marker
            $tempMarkerAnchor = '#########SUP#########';
            $patterntempMarkerAnchor = '/' . $tempMarkerAnchor . '/';
            $pObj->content = preg_replace($patternFootnoteAnchors, $tempMarkerAnchor, $pObj->content);


            $nr = 1;
            $limitTempMarkerAnchor = 1;

            foreach ($footnoteAnchors as $index => $footnoteAnchor) {

                // get footnote text and remove data span
                preg_match($patternDataAttrFootnoteAnchor, $footnoteAnchor, $matchesFootnoteData);

                if (sizeof($matchesFootnoteData) == 4) {
                    $footnotes[] = ['data' => $matchesFootnoteData[2], 'nr' => $nr];
                    $footnoteAnchor = preg_replace($patternDataAttrFootnoteAnchor, '', $footnoteAnchor);
                }

                // set anchor numbers
                $footnoteAnchor = str_replace(
                    [self::MARKER_FOOTNOTE_ANCHOR_NR, urlencode(self::MARKER_FOOTNOTE_ANCHOR_NR)],
                    $nr,
                    $footnoteAnchor
                );

                // replace first (current) temp anchor marker in content by modified footnote anchor
                $pObj->content = preg_replace ( $patterntempMarkerAnchor, $footnoteAnchor, $pObj->content, $limitTempMarkerAnchor);

                $footnoteAnchors[$index] = $footnoteAnchor;

                $nr++;
            }
        }


        // DebuggerUtility::var_dump($footnotes);

        if (sizeof($footnotes)) {
            $patternContainer = '/(' . self::MARKER_FOOTNOTES_START . ')([\w\W]*)(?=' . self::MARKER_FOOTNOTES_END . ')(' . self::MARKER_FOOTNOTES_END . ')/';
            preg_match($patternContainer, $pObj->content, $matches_container);
            if(sizeof($matches_container) == 4) {

                $containerFootnotes = $matches_container[2];

                $footnotesHtml = '';

                // @todo fill $containerFootnotes with $footnotes content
                foreach ($footnotes as $footnote) {


                    $footnotesHtml .= '<li id="fn-content-' . $footnote['nr'] . '">'
                                    .'<a href="#fn-anchor-' . $footnote['nr'] . '" title="Zurück zur Textstelle">[' . $footnote['nr'] . ']</a>'
                                    . ' ' . $footnote['data']
                                    .'</li>';

                }


                $containerFootnotes = str_replace(self::MARKER_FOOTNOTES, $footnotesHtml, $containerFootnotes);
            }
        }





        $patternReplaceContainer = '/' . self::MARKER_FOOTNOTES_START . '[\w\W]*(?=' . self::MARKER_FOOTNOTES_END . ')' . self::MARKER_FOOTNOTES_END . '/';
        $pObj->content = preg_replace ( $patternReplaceContainer, $containerFootnotes, $pObj->content);
    }


}

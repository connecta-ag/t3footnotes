<?php
namespace CAG\T3footnotes\Hooks;



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

    /**
     * @param array $params
     * @param \TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController $pObj
     */
    public function generateFootnotes($params, $pObj)
    {

        $containerFootnotes = '';
        $pattern_footnotes = '/<sup[ ]+class="t3foonote">(?:.(?!\<\/sup\>))*.<\/sup>/i';
        $matches_footnotes = [];
        $footnotes = [];
        $footnoteAnchors = [];

        preg_match_all($pattern_footnotes, $pObj->content, $matches_footnotes, PREG_PATTERN_ORDER);

        if (sizeof($matches_footnotes)) {
            $footnoteAnchors = $matches_footnotes[0];

            // @todo create footnotes


            // @todo replace number placeholder in footnotes anchors


        }


        DebuggerUtility::var_dump($footnotes);

        if (sizeof($footnotes)) {
            $pattern_container = '/(' . self::MARKER_FOOTNOTES_START . ')([\w\W]*)(?=' . self::MARKER_FOOTNOTES_END . ')(' . self::MARKER_FOOTNOTES_END . ')/';
            preg_match($pattern_container, $pObj->content, $matches_container);
            if(sizeof($matches_container) == 4) {

                $containerFootnotes = $matches_container[2];


                // @todo fill $containerFootnotes with $footnotes content



            }
        }





        $pattern_replace_container = '/' . self::MARKER_FOOTNOTES_START . '[\w\W]*(?=' . self::MARKER_FOOTNOTES_END . ')' . self::MARKER_FOOTNOTES_END . '/';
        $pObj->content = preg_replace ( $pattern_replace_container, $containerFootnotes, $pObj->content);
    }


}

/*
 * This file written for the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * The TYPO3 project - inspiring people to share!
 */

'use strict';

/**
 * This is a TYPO3 plugin for rte_ckeditor which provides
 * footnotes functionality.
 *
 * Based on / developed for CKEditor 4.11
 *
 * Footnotes can be added in one or multiple RTE instances on a page. The
 * entered footnotes will be collected, linked to
 * of the page and / or as tooltips.
 *
 */
(function() {

  CKEDITOR.plugins.add('t3footnotes', {

    onLoad: function() {

      // adding some basic CSS to make the FNs have a look already
      CKEDITOR.addCss(
          'a.t3foonotes-anchor {' +
            'color: green;' +
            'text-width: bold;' +
          '}'
      );

    },

    init: function(editor) {

      // let's add our button to the RTE and connect its functionality
      editor.ui.addButton('T3footnotes', {
        label: 'Insert Footnote', // TODO: add locallang label
        toolbar: 'insert',
        command: 'insertFootnote',
        icon: this.path + 'icons/t3footnotes.png',
      });

      // adding the insert footnote modal dialog
      editor.addCommand('insertFootnote', new CKEDITOR.dialogCommand('openInsertFootnoteDialog'));

      // TODO: maybe migrate from inline css (see onLoad function) to a css file here
      // var pluginDirectory = this.path;
      // editor.addContentsCss( pluginDirectory + 'styles/example.css' );

      CKEDITOR.dialog.add('openInsertFootnoteDialog', function(editor) {


        // vars to lokalize
        var dialogTitle = 'Insert Footnote Dialog'; // TODO: add locallang label
        var dialogTabLabel = 'Insert Footnote Content'; // TODO: add locallang label
        var dialogElementFootnotetextLabel = 'Footnote Text'; // TODO: add locallang label
        var footnoteAnchorTitle = 'Zur Erläuterung'; // TODO: add locallang label


        return {

          title: dialogTitle,
          minWidth: 400,
          minHeight: 200,

          // adding the modal dialog content
          contents: [{

            id: 'content',
            label: dialogTabLabel,

            // adding elements to the dialog
            elements: [{
              type: 'text',
              id: 'footnotetext',
              label: dialogElementFootnotetextLabel
            }]
          }],

          // On show action of the dialog checking for edit mode.
          // If a foot note choosed select the foot note and set foot note content in modal dialog.
          onShow: function() {

            var footnoteContent = '', supEl;
            var dialog = this;
            var selection = editor.getSelection();
            var el = selection.getStartElement();


            // do some thing if in a foot note
            if(el.hasClass('t3foonotes-anchor')) {
              // select footnote
              supEl = el.getParent();
              if (supEl.hasClass('t3foonote')) {
                selection.selectElement(supEl);
              }
              // set dialog value
              footnoteContent = el.getAttribute('data-fncontent');
              if (footnoteContent != '') {
                dialog.setValueOf('content','footnotetext', footnoteContent);
              }
            }
          },



          // the following actions are to be executed after clicking OK inside the modal dialog
          onOk: function() {

            var title = footnoteAnchorTitle;
            var dialog = this;
            var htmlFootnote = '';
            var markerFootnoteNumber = '#n#';

            // get text from tab content and input field footnotetext
            var footnoteContent = dialog.getValueOf( 'content', 'footnotetext' );

            if (footnoteContent != '') {
              htmlFootnote = '' +
                '<sup class="t3foonote">' +
                '<a id="fn-anchor-' + markerFootnoteNumber + '"' +
                ' class="t3foonotes-anchor"' +
                ' href="#fn-content-' + markerFootnoteNumber + '"' +
                ' title="' + title + '"' +
                ' data-fncontent="' + footnoteContent + '"' +
                '>' +
                '[' + markerFootnoteNumber + ']' +
                '</a>' +
                '</sup>';
            }

            editor.insertHtml(htmlFootnote);

          }
        };
      });

    }
  });
})();

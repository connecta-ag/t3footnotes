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
      CKEDITOR.addCss(
          'span.fn-anchor {' +
            'color: green' +
          '}',
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
      editor.addCommand('insertFootnote',
          new CKEDITOR.dialogCommand('openInsertFootnoteDialog'));

      CKEDITOR.dialog.add('openInsertFootnoteDialog', function(editor) {

        var abc = 123;

        return {

          title: 'Insert Footnote Dialog', // TODO: add locallang label
          minWidth: 400,
          minHeight: 200,

          onShow: function() {

            // alert('hey ho onShow');

          },

          // adding the modal dialog content
          contents: [{

            id: 'content',
            label: 'Insert Footnote Content', // TODO: add locallang label

            // adding elements to the dialog
            elements: [{
              type: 'text',
              id: 'footnotetext',
              label: 'Footnote Text' // TODO: add locallang label
              // validate: CKEDITOR.dialog.validate.notEmpty('Please enter footnote text') // TODO: add locallang label
            }]
          }]
        };
      });

      // first working example, simple – without any text input opportunity; TODO: might have to be deleted again, later
      // editor.addCommand('insertFootnote', {
      //
      //   // exec: function (editor) {
      //   //   console.log('CAG JR: hey! this ist the command "insertFootnote"');
      //   // }
      //
      //   exec: openInsertFootnoteModal
      //
      // });

      // TODO: see, if it's needed or not anymore, in the end
      // function openInsertFootnoteModal(editor) {
      //
      //   // ...
      //   var selection = editor.getSelection(),
      //       content = 'No text selected'; // TODO: add locallang label
      //
      //   if (selection && selection.getSelectedText()) {
      //     content = 'Add footnote content referring to selected text: ' + selection.getSelectedText(); // TODO: add locallang label
      //   }
      //
      //   // TODO: remove again - it's just for showing how to add content to the editor's html
      //   // var now = new Date();
      //   // editor.insertHtml( 'The current date and time is: <em>' + now.toString() + '</em>' );
      //
      //
      //   require([
      //       'TYPO3/CMS/Backend/Modal'
      //   ], function (Modal) {
      //     Modal.show(
      //         'Insert Footnote', // TODO: add locallang label
      //         content
      //     );
      //   });
      // }

      console.log('CAG JR: hey, init t3footnotes here!');

    },

  });

})();

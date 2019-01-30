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
 * Footnotes can be added in one or multiple RTE instances on a page. The
 * entered footnotes will be collected, linked to
 * of the page and / or as tooltips.
 *
 */
(function() {

  CKEDITOR.plugins.add('t3footnotes', {

    init: function(editor) {

      // editor.ui.addButton('t3footnotes', {
      //   label: 'Insert Footnote',
      //   command: 't3footnotes',
      //   toolbar: 'insert',
      //   icon: this.path + 'icons/ckeditor-fa.png',
      // });
      console.log('CAG JR: hey, init t3footnotes here!');

    }

  });

})();

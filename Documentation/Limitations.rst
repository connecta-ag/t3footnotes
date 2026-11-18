:navigation-title: Known limits

..  include:: /Includes.rst.txt

..  _known-limits:

============
Known limits
============

*   **Editor formatting.** New notes in 2.0 use plain text. Bold, italic and
    links in the dialog are planned for 2.1. See :ref:`legacy-note-formatting`
    for how the editor handles existing markup.
*   **Rendering outside page generation.** Put notes and list markers in the
    page content TYPO3 generates before expanding ``USER_INT`` and
    ``COA_INT``. Standalone error handlers such as a Fluid 404 template
    also bypass this step. Ordinary pages with ``config.no_cache`` and
    TYPO3 page-based 404 handlers can render notes. See
    :ref:`caching-and-extension-event`.
*   **Legacy notes inside links.** The frontend puts the numbered reference
    after the enclosing link. Repair the stored markup before opening it
    in CKEditor, which cannot keep the note intact when it parses nested
    links. See :ref:`legacy-notes-inside-links`.
*   **Supported versions.** Version 2.0 supports the TYPO3 14.3 release
    series and requires PHP 8.4 or later within PHP 8. CI tests PHP 8.4 and
    8.5. See :ref:`installation`.

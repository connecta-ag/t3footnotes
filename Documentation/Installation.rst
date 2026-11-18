:navigation-title: Installation

..  include:: /Includes.rst.txt

..  _installation:

============
Installation
============

The extension requires TYPO3 14.3 and PHP 8.4 or later. The page renderer
uses the HTML DOM introduced in PHP 8.4.

..  _installation-composer:

Composer mode
=============

From the TYPO3 project directory, run:

..  code-block:: bash

    composer require connecta-ag/t3footnotes:^2.0

Continue with :ref:`installation-site-set`.

..  _installation-classic:

Classic mode
============

In the Extension Manager, search the TYPO3 Extension Repository (TER) for
``t3footnotes``, then import and activate the extension. Starting with
TYPO3 14.3.8, the Extension Manager downloads the original TER ZIP when
the extension index provides its checksum. For TYPO3 14.3.0 through 14.3.7,
download the extension ZIP from TER and upload it manually in the Extension
Manager. See
`Important #110629: Extension Manager downloads ZIP archives from TER
<https://docs.typo3.org/c/typo3/cms-core/main/en-us/Changelog/14.3.x/Important-110629-ExtensionManagerDownloadsZipArchives.html>`__.

..  _installation-site-set:

Enable the site set
===================

In Site Management, add ``connecta-ag/t3footnotes`` to the site sets for
each site that should render notes. You can also declare it as a dependency
in your own site set. The extension leaves the output of sites without the
set unchanged. See :ref:`site-settings` for the available settings.

..  _installation-editor:

Add the editor button
=====================

In the active rich text editor (RTE) preset, import the footnote plugin
and add the ``t3footnote`` toolbar item. The import loads the widget and its
stylesheet. The following example starts from TYPO3's default preset. For
an existing preset, keep its imports and toolbar items and add the footnote
entries.

..  code-block:: yaml

    imports:
      - { resource: 'EXT:rte_ckeditor/Configuration/RTE/Default.yaml' }
      - { resource: 'EXT:t3footnotes/Configuration/RTE/Plugin.yaml' }

    editor:
      config:
        toolbar:
          items: [heading, bold, italic, link, t3footnote, undo, redo]

Flush the TYPO3 caches. Open a rich-text record and check for the
:guilabel:`Footnote` button. See :ref:`editor` for authoring and
:ref:`rte-processing` for custom preset requirements.

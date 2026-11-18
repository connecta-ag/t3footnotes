:navigation-title: Upgrade

..  include:: /Includes.rst.txt

..  _upgrade:

================
Upgrade from 1.x
================

Projects that required ``cag/t3footnotes`` from the GitHub repository must
change that Composer requirement to ``connecta-ag/t3footnotes``. The site
set ``connecta-ag/t3footnotes`` and the editor module prefix
``@connecta-ag/t3footnotes/`` are new in 2.0 under the new name. Update any
direct module imports. The extension key and PHP namespace stay the same.

1.   Upgrade TYPO3 to 14.3 and run PHP 8.4 or later. Follow :ref:`installation`
    to install 2.0 and enable the site set.
2.   Remove the CKEditor 4 external plugin and the ``allowedContent`` setting
    from your preset. Import the provided CKEditor 5 preset fragment and add
    the ``t3footnote`` item.
3.   Remove the old static TypoScript include, Extbase footnote list plugin
    records and overridden List or Item Fluid templates. Their settings and
    template paths no longer run. Use the default placement, site selector,
    an explicit Fluid marker, or your existing 1.x marker wrapper. Put
    ``###FOOTNOTES###`` inside an ``ol`` if you keep a wrapper. In particular,
    replace ``<f:cObject typoscriptObjectPath="lib.t3footnotes" />`` with
    ``<div data-t3footnotes-list></div>`` or emit the retained
    ``###FOOTNOTES_START###`` and ``###FOOTNOTES_END###`` wrapper directly.
    Without a project-defined TypoScript replacement, the old call raises a
    Fluid error because ``lib.t3footnotes`` no longer exists.
4.   Update any custom styles or scripts that depend on the old plugin
    markup. Flush page caches and verify references, return links and heading
    levels on the upgraded sites.
5.   Move any footnotes emitted from non-cacheable objects or custom error
    handlers to normal cacheable page content.

Existing CKEditor 4 records keep their stored markup and require no database
conversion. Frontend sanitization still applies to the special note payload.
See :ref:`editor` for the rule that applies when editing a legacy HTML note.

..  _release-metadata:

Release metadata
================

TYPO3 14.3 reads the Classic-mode version and package metadata from
``composer.json``. The extension omits ``ext_emconf.php``.
TER has accepted Composer-only uploads since 14 September 2026.
Use a bare numeric tag such as ``2.0.0`` when publishing a release.
See the `TYPO3 announcement <https://news.typo3.com/article/ter-speaks-composerjson-now>`__,
the
`official Tailor upload guide <https://docs.typo3.org/other/typo3/tailor/main/en-us/Index.html>`__
and the
`TYPO3 composer.json reference <https://docs.typo3.org/m/typo3/reference-coreapi/14.3/en-us/ExtensionArchitecture/FileStructure/ComposerJson.html>`__.


..  _upstream-requests:

Upstream requests
=================

*   `Issue #3, AllowedContent side effect <https://github.com/connecta-ag/t3footnotes/issues/3>`__.
    The CKEditor 5 widget registers its own model and conversion. The preset
    keeps TYPO3's usual content filtering.
*   `Issue #5, stale ext_emconf version <https://github.com/connecta-ag/t3footnotes/issues/5>`__
    and `PR #6, version correction <https://github.com/connecta-ag/t3footnotes/pull/6>`__.
    The extension uses Composer-only metadata. CI checks a release tag against
    ``extra.typo3/cms.version``, accepting either form in tests. Only a bare numeric tag can publish.
*   `Issue #7, footnote with HTML <https://github.com/connecta-ag/t3footnotes/issues/7>`__.
    Legacy inline HTML renders after sanitization. HTTP(S) URLs can be linked
    automatically. New notes use plain text. Bold, italic and links in the
    dialog are planned for 2.1.
*   `PR #8, sites without footnote setup <https://github.com/connecta-ag/t3footnotes/pull/8>`__.
    A site without the extension's site set leaves its HTML unchanged.
*   `PR #9, TYPO3 11 compatibility <https://github.com/connecta-ag/t3footnotes/pull/9>`__
    and `PR #10, TYPO3 9.5–11 compatibility <https://github.com/connecta-ag/t3footnotes/pull/10>`__
    are superseded for 2.0. The extension supports TYPO3 14.3. The stored
    markup can stay in the database through an upgrade via TYPO3 11–13.
    Run the 2.0 renderer once the installation reaches TYPO3 14.3.
*   `PR #11, CKEditor 5 and TYPO3 13 <https://github.com/connecta-ag/t3footnotes/pull/11>`__.
    Rémy Daniel's widget and dialog are the basis of the 2.0 editor.

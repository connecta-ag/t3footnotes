# Editorial footnotes for TYPO3

Write numbered footnotes in CKEditor 5. TYPO3 renders the references, lists
and return links before caching the page.

Requires TYPO3 14.3 and PHP 8.4 or later.

- **Composer mode:** `composer require connecta-ag/t3footnotes:^2.0`.
- **Classic mode:** Import and activate `t3footnotes` in the Extension
  Manager. On TYPO3 14.3.0 through 14.3.7, download the ZIP from the TYPO3
  Extension Repository and upload it manually.

Enable the `connecta-ag/t3footnotes` site set. Import
`EXT:t3footnotes/Configuration/RTE/Plugin.yaml` in the active editor preset
and add the `t3footnote` toolbar item. Flush TYPO3's caches.

[Published manual](https://docs.typo3.org/p/connecta-ag/t3footnotes/main/en-us/)
(available after the first release) · [Changelog](CHANGELOG.md) ·
[GPL-2.0-or-later license](LICENSE)

## Contributing

In a source checkout, install dependencies with `composer update`. Run
`composer test:unit`, `composer test:functional`, `composer test:phpstan`
and `composer test:coding-standards`. The functional suite uses SQLite.

Render the manual from the extension root with the official TYPO3 image.

```sh
docker run --rm -v "$PWD:/project" \
  ghcr.io/typo3-documentation/render-guides:latest \
  --config=Documentation --no-progress --fail-on-log
```

Originally developed by Jochen Rieger. Maintained by [Connecta AG](https://www.connecta.ag/).

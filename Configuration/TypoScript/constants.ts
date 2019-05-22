
plugin.tx_t3footnotes {
    view {
        # cat=plugin.tx_t3footnotes_list/file; type=string; label=Path to template root (FE)
        templateRootPath = EXT:t3footnotes/Resources/Private/Templates/
        # cat=plugin.tx_t3footnotes_list/file; type=string; label=Path to template partials (FE)
        partialRootPath = EXT:t3footnotes/Resources/Private/Partials/
        # cat=plugin.tx_t3footnotes_list/file; type=string; label=Path to template layouts (FE)
        layoutRootPath = EXT:t3footnotes/Resources/Private/Layouts/
    }
    persistence {
        # cat=plugin.tx_t3footnotes_list//a; type=string; label=Default storage PID
        storagePid =
    }
}

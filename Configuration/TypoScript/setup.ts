
plugin.tx_t3footnotes {
    view {
        templateRootPaths.0 = EXT:t3footnotes/Resources/Private/Templates/
        templateRootPaths.1 = {$plugin.tx_t3footnotes.view.templateRootPath}
        partialRootPaths.0 = EXT:t3footnotes/Resources/Private/Partials/
        partialRootPaths.1 = {$plugin.tx_t3footnotes.view.partialRootPath}
        layoutRootPaths.0 = EXT:t3footnotes/Resources/Private/Layouts/
        layoutRootPaths.1 = {$plugin.tx_t3footnotes.view.layoutRootPath}
    }
    persistence {
        storagePid = {$plugin.tx_t3footnotes.persistence.storagePid}
        #recursive = 1
    }
    features {
        #skipDefaultArguments = 1
        # if set to 1, the enable fields are ignored in BE context
        ignoreAllEnableFieldsInBe = 0
        # Should be on by default, but can be disabled if all action in the plugin are uncached
        requireCHashArgumentForActionArguments = 1
    }
    mvc {
        #callDefaultActionIfActionCantBeResolved = 1
    }

    settings {
        abc = 123
    }

}

# copy plugin config to lib-object
lib.t3footnotes = COA
lib.t3footnotes {
    10 =< tt_content.list.20.t3footnotes_list
}

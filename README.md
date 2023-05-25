# t3footnotes
TYPO3 extension to add footnotes to TYPO3's integrated RTE CKEditor

## Use of this plugin

#### Install over the composer
```
"repositories": [
  ...
  {
    "type": "git",
    "url": "https://github.com/connecta-ag/t3footnotes"
  },
  ...
],
```
```
"require": {
  ...
  "cag/t3footnotes": "dev-master",
  ...
},
```

#### Add TypoScript configuration

As include in your main template (setup, constants)

Contants
```
# include t3footnotes base contants
<INCLUDE_TYPOSCRIPT: source="FILE:EXT:t3footnotes/Configuration/TypoScript/constants.ts">
```
Setup
```
# include original t3t3footnotes base typoscript
<INCLUDE_TYPOSCRIPT: source="FILE:EXT:t3footnotes/Configuration/TypoScript/setup.ts">
```

or over Include Static Templates in DB-Typoscript-Template


#### Add JS-Plugin Configuration to your RTE Configuration

```
imports:
  # Import Footnotes RTE Plugin Configuration
  - { resource: "EXT:t3footnotes/Configuration/RTE/Plugin.yaml"}
```


### Implement t3footnotes plugin in all templates you need

Use in Templates to create a container for printing out of footnotes

```<f:cObject typoscriptObjectPath="lib.t3footnotes" />```


enjoy


const translations = {
    en: {
        'Footnote': 'Footnote',
        'Footnote text': 'Footnote text',
        'Insert footnote': 'Insert footnote',
        'Edit footnote': 'Edit footnote',
        'Delete footnote': 'Delete footnote',
        'Footnote text is required.': 'Footnote text is required.',
        'Changing the text replaces the original formatting.': 'Changing the text replaces the original formatting.',
        'Footnote toolbar': 'Footnote toolbar',
        'No text': 'No text',
        'Plain text. The website numbers notes in reading order.': 'Plain text. The website numbers notes in reading order.',
    },
    de: {
        'Footnote': 'Fußnote',
        'Footnote text': 'Fußnotentext',
        'Insert footnote': 'Fußnote einfügen',
        'Edit footnote': 'Fußnote bearbeiten',
        'Delete footnote': 'Fußnote löschen',
        'Footnote text is required.': 'Fußnotentext ist erforderlich.',
        'Changing the text replaces the original formatting.': 'Wenn Sie den Text ändern, wird die ursprüngliche Formatierung ersetzt.',
        'Footnote toolbar': 'Fußnoten-Werkzeugleiste',
        'No text': 'Kein Text',
        'Plain text. The website numbers notes in reading order.': 'Nur Text. Die Website nummeriert Fußnoten in Lesereihenfolge.',
    },
    fr: {
        'Footnote': 'Note de bas de page',
        'Footnote text': 'Texte de la note de bas de page',
        'Insert footnote': 'Insérer une note de bas de page',
        'Edit footnote': 'Modifier la note de bas de page',
        'Delete footnote': 'Supprimer la note de bas de page',
        'Footnote text is required.': 'Le texte de la note de bas de page est obligatoire.',
        'Changing the text replaces the original formatting.': 'La modification du texte remplace la mise en forme d’origine.',
        'Footnote toolbar': 'Barre d’outils de la note de bas de page',
        'No text': 'Aucun texte',
        'Plain text. The website numbers notes in reading order.': 'Texte brut. Le site numérote les notes dans l’ordre de lecture.',
    },
};

const registry = window.CKEDITOR_TRANSLATIONS ??= {};
for (const [language, dictionary] of Object.entries(translations)) {
    const entry = registry[language] ??= {};
    Object.assign(entry.dictionary ??= {}, dictionary);
}

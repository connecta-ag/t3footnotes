import { Plugin } from '@ckeditor/ckeditor5-core';
import { ClickObserver, DomEventObserver } from '@ckeditor/ckeditor5-engine';
import { IconPencil, IconRemove } from '@ckeditor/ckeditor5-icons';
import { BalloonPanelView, ButtonView, Dialog, LabelView, TextareaView, View } from '@ckeditor/ckeditor5-ui';
import { uid } from '@ckeditor/ckeditor5-utils';
import {
    Widget,
    WidgetToolbarRepository,
    isWidget,
    toWidget,
    viewToModelPositionOutsideModelElement,
} from '@ckeditor/ckeditor5-widget';
import './translations.js';

const icon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill="currentColor" d="M3 2h14v12h-5l-4 4v-4H3zm2 2v8h5v2l2-2h3V4zm2 2h6v1H7zm0 3h6v1H7z"/></svg>';
const marker = { name: true, classes: 't3foonote' };

// The dialog and the note balloon live outside the editable, so the prefixed contentsCss cannot reach them.
function loadInterfaceStyles() {
    const module = new URL(import.meta.url);
    const href = new URL(`../Css/editor-ui.css${module.search}`, module).href;
    if (!document.querySelector(`link[data-t3footnotes="ui"]`)) {
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = href;
        link.dataset.t3footnotes = 'ui';
        document.head.append(link);
    }
}

class DoubleClickObserver extends DomEventObserver {
    domEventType = 'dblclick';

    onDomEvent(domEvent) {
        this.fire(domEvent.type, domEvent);
    }
}

class FootnotePreview extends View {
    constructor(locale) {
        super(locale);
        const bind = this.bindTemplate;
        this.set('text', '');
        this.setTemplate({
            tag: 'span',
            attributes: {
                class: ['ck', 'ck-footnote-preview', bind.if('text', 'ck-footnote-preview_empty', text => !text)],
            },
            children: [{ text: bind.to('text', text => text || locale.t('No text')) }],
        });
    }
}

class FootnoteForm extends View {
    constructor(locale, initialText, hasLegacyHtml) {
        super(locale);
        const t = locale.t;
        const bind = this.bindTemplate;
        const id = uid();
        this.initialText = initialText;
        this.hasLegacyHtml = hasLegacyHtml;
        this.set('errorText', null);

        this.textarea = new TextareaView(locale);
        this.textarea.set({ id: `ck-footnote-text-${id}`, minRows: 5, maxRows: 14 });
        this.textarea.extendTemplate({ attributes: { class: 'ck-footnote-form__field', spellcheck: 'true' } });
        this.textarea.bind('hasError').to(this, 'errorText', text => Boolean(text));
        this.textarea.bind('ariaDescribedById').to(this, 'errorText',
            text => [text ? `ck-footnote-error-${id}` : null, `ck-footnote-hint-${id}`].filter(Boolean).join(' '));

        this.label = new LabelView(locale);
        this.label.set({ text: t('Footnote text'), for: this.textarea.id });

        const children = [
            this.label,
            this.textarea,
            {
                tag: 'p',
                attributes: {
                    id: `ck-footnote-error-${id}`,
                    class: ['ck', 'ck-footnote-form__error', bind.if('errorText', 'ck-hidden', text => !text)],
                    role: 'alert',
                },
                children: [{ text: bind.to('errorText', text => text ?? '') }],
            },
            {
                tag: 'p',
                attributes: { id: `ck-footnote-hint-${id}`, class: ['ck', 'ck-footnote-form__hint'] },
                children: [{ text: t('Plain text. The website numbers notes in reading order.') }],
            },
        ];
        if (hasLegacyHtml) {
            children.push({
                tag: 'p',
                attributes: { class: ['ck', 'ck-footnote-form__notice'] },
                children: [{ text: t('Changing the text replaces the original formatting.') }],
            });
        }
        this.setTemplate({
            tag: 'div',
            attributes: { class: ['ck', 'ck-footnote-form'] },
            children,
        });
    }

    render() {
        super.render();
        this.textarea.value = this.initialText;
        this.listenTo(this.textarea, 'input', () => {
            this.errorText = null;
        });
        // Ctrl+Enter and Cmd+Enter save on every platform. Plain Enter keeps adding line breaks.
        this.listenTo(this.element, 'keydown', (_event, domEvent) => {
            if (domEvent.key === 'Enter' && (domEvent.ctrlKey || domEvent.metaKey) && !domEvent.isComposing) {
                domEvent.preventDefault();
                domEvent.stopPropagation();
                this.fire('submit');
            }
        });
    }

    focus() {
        this.textarea.focus();
    }

    readText() {
        const value = this.textarea.element.value;
        if (value.trim() === '' && !(this.hasLegacyHtml && value === this.initialText)) {
            this.errorText = this.t('Footnote text is required.');
            this.focus();
            return null;
        }
        return value;
    }
}

export class Footnote extends Plugin {
    static get pluginName() {
        return 'Footnote';
    }

    static get requires() {
        return [Widget, WidgetToolbarRepository, Dialog];
    }

    init() {
        const { model, editing, ui } = this.editor;
        loadInterfaceStyles();
        this.set('previewText', '');
        model.schema.register('t3footnote', {
            allowWhere: '$text',
            isInline: true,
            isObject: true,
            allowAttributes: ['content', 'legacyHtml'],
        });
        this.defineConverters();
        editing.mapper.on('viewToModelPosition',
            viewToModelPositionOutsideModelElement(model, element => element.hasClass('t3foonote')));

        ui.componentFactory.add('t3footnote', locale => {
            const button = new ButtonView(locale);
            button.set({ label: this.editor.t('Footnote'), icon, tooltip: true });
            const refresh = () => {
                const selection = model.document.selection;
                button.isEnabled = !this.editor.isReadOnly && (
                    this.selectedNote() !== null || model.schema.checkChild(selection.focus, 't3footnote')
                );
                button.isOn = this.selectedNote() !== null;
            };
            button.listenTo(model.document.selection, 'change:range', refresh);
            button.listenTo(this.editor, 'change:isReadOnly', refresh);
            button.on('execute', () => this.openDialog(this.selectedNote()));
            refresh();
            return button;
        });
        this.defineBalloonItems();

        editing.view.addObserver(ClickObserver);
        editing.view.addObserver(DoubleClickObserver);
        this.listenTo(editing.view.document, 'click', (event, data) => {
            const note = this.noteFromDom(data.domTarget);
            if (note) {
                data.preventDefault();
                event.stop();
                model.change(writer => writer.setSelection(note, 'on'));
            }
        }, { context: '$capture' });

        // A single click selects the note and shows its text; a double click opens it, like Enter.
        this.listenTo(editing.view.document, 'dblclick', (event, data) => {
            const note = this.noteFromDom(data.domTarget);
            if (note && !this.editor.isReadOnly) {
                data.preventDefault();
                event.stop();
                model.change(writer => writer.setSelection(note, 'on'));
                this.openDialog(note);
            }
        }, { context: '$capture' });

        this.listenTo(editing.view.document, 'enter', (event, data) => {
            const note = this.selectedNote();
            if (note && !data.isSoft && !this.editor.isReadOnly) {
                data.preventDefault();
                event.stop();
                this.openDialog(note);
            }
        }, { context: '$capture', priority: 'high' });

        const refreshPreview = () => {
            this.previewText = this.selectedNote()?.getAttribute('content') ?? '';
        };
        this.listenTo(model.document.selection, 'change:range', refreshPreview);
        this.listenTo(model.document, 'change:data', refreshPreview);
    }

    afterInit() {
        const { t } = this.editor;
        this.editor.plugins.get(WidgetToolbarRepository).register('t3footnote', {
            ariaLabel: t('Footnote toolbar'),
            items: ['t3footnotePreview', '|', 't3footnoteEdit', 't3footnoteDelete'],
            balloonClassName: 'ck-toolbar-container ck-footnote-balloon',
            getRelatedElement: selection => {
                const element = selection.getSelectedElement();
                return element && isWidget(element) && element.hasClass('t3foonote') ? element : null;
            },
            // Below the marker, so the sentence the note belongs to stays readable.
            positions: [
                BalloonPanelView.defaultPositions.southArrowNorth,
                BalloonPanelView.defaultPositions.southArrowNorthWest,
                BalloonPanelView.defaultPositions.southArrowNorthEast,
                BalloonPanelView.defaultPositions.northArrowSouth,
                BalloonPanelView.defaultPositions.northArrowSouthWest,
                BalloonPanelView.defaultPositions.northArrowSouthEast,
            ],
        });
    }

    defineBalloonItems() {
        const { model, ui, t } = this.editor;
        ui.componentFactory.add('t3footnotePreview', locale => {
            const preview = new FootnotePreview(locale);
            preview.bind('text').to(this, 'previewText');
            return preview;
        });
        ui.componentFactory.add('t3footnoteEdit', locale => {
            const button = new ButtonView(locale);
            button.set({ label: t('Edit footnote'), icon: IconPencil, tooltip: true });
            button.bind('isEnabled').to(this.editor, 'isReadOnly', readOnly => !readOnly);
            button.on('execute', () => this.openDialog(this.selectedNote()));
            return button;
        });
        ui.componentFactory.add('t3footnoteDelete', locale => {
            const button = new ButtonView(locale);
            button.set({ label: t('Delete footnote'), icon: IconRemove, tooltip: true, class: 'ck-footnote-delete' });
            button.bind('isEnabled').to(this.editor, 'isReadOnly', readOnly => !readOnly);
            button.on('execute', () => {
                const note = this.selectedNote();
                if (note) {
                    model.change(writer => writer.remove(note));
                    this.editor.editing.view.focus();
                }
            });
            return button;
        });
    }

    noteFromDom(domTarget) {
        const widget = domTarget.closest?.('sup.t3foonote.ck-widget');
        if (!widget) {
            return null;
        }
        const { editing } = this.editor;
        const note = editing.mapper.toModelElement(editing.view.domConverter.mapDomToView(widget));
        return note?.is('element', 't3footnote') ? note : null;
    }

    selectedNote() {
        const selected = this.editor.model.document.selection.getSelectedElement();
        return selected?.is('element', 't3footnote') ? selected : null;
    }

    openDialog(note) {
        const editor = this.editor;
        const { model, t } = editor;
        const position = model.document.selection.focus;
        if (editor.isReadOnly || (!note && !model.schema.checkChild(position, 't3footnote'))) {
            return;
        }

        const dialog = editor.plugins.get(Dialog);
        const original = note?.getAttribute('content') ?? '';
        const form = new FootnoteForm(editor.locale, original, Boolean(note?.hasAttribute('legacyHtml')));
        const save = () => {
            const content = form.readText();
            if (content === null) {
                return;
            }
            model.change(writer => {
                if (!note) {
                    const element = writer.createElement('t3footnote', { content });
                    model.insertObject(element, position, undefined, { setSelection: 'after' });
                } else if (content !== original) {
                    writer.setAttribute('content', content, note);
                    writer.removeAttribute('legacyHtml', note);
                }
            });
            dialog.hide();
        };
        form.on('submit', save);

        const actionButtons = [];
        if (note) {
            actionButtons.push({
                label: t('Delete footnote'),
                icon: IconRemove,
                withText: true,
                class: 'ck-footnote-dialog__delete',
                onExecute: () => {
                    model.change(writer => writer.remove(note));
                    dialog.hide();
                },
            });
        }
        actionButtons.push(
            { label: t('Cancel'), withText: true, onExecute: () => dialog.hide() },
            {
                label: t('Save'),
                class: 'ck-button-action',
                withText: true,
                keystroke: 'Ctrl+Enter',
                withKeystroke: true,
                onExecute: save,
            },
        );

        dialog.show({
            id: 't3footnote',
            isModal: true,
            className: 'ck-footnote-dialog',
            title: note ? t('Edit footnote') : t('Insert footnote'),
            content: form,
            actionButtons,
            onShow: () => form.focus(),
            onHide: () => {
                form.destroy();
                editor.editing.view.focus();
            },
        });
    }

    defineConverters() {
        const { conversion, data } = this.editor;
        // Consume the complete legacy marker as one model object. Its children
        // are data for this widget and must never be converted as editor text.
        conversion.for('upcast').add(dispatcher => dispatcher.on('element:sup', (_event, input, api) => {
            const { viewItem } = input;
            if (!viewItem.hasClass('t3foonote') || !api.consumable.test(viewItem, marker)) {
                return;
            }
            const source = Array.from(viewItem.getChildren()).find(
                child => child.is('element', 'span') && child.hasClass('t3foonotes-anchor-data')
            );
            const attributes = { content: '' };
            if (source) {
                const dom = data.processor.domConverter.viewToDom(source);
                attributes.content = dom.textContent;
                if (dom.children.length) {
                    attributes.legacyHtml = dom.innerHTML;
                }
            }
            const note = api.writer.createElement('t3footnote', attributes);
            if (api.safeInsert(note, input.modelCursor)) {
                api.consumable.consume(viewItem, marker);
                api.updateConversionResult(note, input);
            }
        }, { priority: 'high' }));

        conversion.for('dataDowncast').elementToElement({
            model: { name: 't3footnote', attributes: ['content', 'legacyHtml'] },
            view: (note, { writer }) => {
                const sup = writer.createContainerElement('sup', { class: 't3foonote' });
                const anchor = writer.createContainerElement('a', {
                    class: 't3foonotes-anchor',
                    href: '#fn-content-{n}',
                    id: 'fn-anchor-{n}',
                }, [writer.createText('[{n}]')]);
                const legacy = note.getAttribute('legacyHtml');
                const attributes = {
                    class: 't3foonotes-anchor-data',
                    style: 'display: none',
                };
                const source = legacy
                    ? writer.createRawElement('span', attributes, (element, domConverter) => {
                        // The data converter uses an inert document for the legacy payload.
                        element.append(domConverter.viewToDom(data.processor.toView(legacy)));
                    })
                    : writer.createContainerElement('span', attributes, [writer.createText(note.getAttribute('content'))]);
                writer.insert(writer.createPositionAt(sup, 0), [anchor, source]);
                return sup;
            },
        });

        conversion.for('editingDowncast').elementToElement({
            model: { name: 't3footnote', attributes: ['content'] },
            view: (note, { writer }) => {
                const content = note.getAttribute('content');
                const label = this.editor.t('Footnote');
                // The stylesheet draws the note mark; the hidden word names it for screen readers and find.
                const sup = writer.createContainerElement('sup', {
                    class: 't3foonote',
                    title: content || label,
                }, [writer.createContainerElement('span', { class: 't3foonote__label' }, [writer.createText(label)])]);
                return toWidget(sup, writer, { label: content ? `${label}: ${content}` : label });
            },
        });
    }
}

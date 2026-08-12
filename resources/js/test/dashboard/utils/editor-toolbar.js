import { MEDIA_UPLOAD_URL } from '../core/config.js';
import { showAlert } from './custom-alert.js';

/**
 * EasyMDE's own preview renders markdown with its bundled parser, which eats
 * the backslash in `\%` and `\$` and therefore disagrees with both the live
 * preview panel and the test engine. The toolbar button is gone; these keyboard
 * shortcuts are the other way in, so they are disabled too. Spread this into
 * every `new EasyMDE({...})` alongside the toolbar.
 */
export const PREMIUM_EDITOR_OPTIONS = {
    shortcuts: {
        togglePreview: null,
        toggleSideBySide: null,
    },
};

export function getPremiumToolbar(activeEditorKey, changeCallback) {
    const toggleBold = window.EasyMDE ? window.EasyMDE.toggleBold : (e) => e.toggleBold();
    const toggleItalic = window.EasyMDE ? window.EasyMDE.toggleItalic : (e) => e.toggleItalic();
    const toggleHeadingSmaller = window.EasyMDE ? window.EasyMDE.toggleHeadingSmaller : (e) => e.toggleHeadingSmaller();
    const toggleBlockquote = window.EasyMDE ? window.EasyMDE.toggleBlockquote : (e) => e.toggleBlockquote();
    const toggleUnorderedList = window.EasyMDE ? window.EasyMDE.toggleUnorderedList : (e) => e.toggleUnorderedList();
    const toggleOrderedList = window.EasyMDE ? window.EasyMDE.toggleOrderedList : (e) => e.toggleOrderedList();

    return [
        { name: "bold", action: toggleBold, className: "bi bi-type-bold", title: "Bold" },
        { name: "italic", action: toggleItalic, className: "bi bi-type-italic", title: "Italic" },
        {
            name: "underline",
            action: (editor) => {
                editor.codemirror.replaceSelection(`<u>${editor.codemirror.getSelection()}</u>`);
                if (changeCallback) changeCallback();
            },
            className: "bi bi-type-underline",
            title: "Underline"
        },
        { name: "heading", action: toggleHeadingSmaller, className: "bi bi-type-h1", title: "Heading" }, "|",
        { name: "quote", action: toggleBlockquote, className: "bi bi-chat-left-quote", title: "Quote" },
        { name: "unordered-list", action: toggleUnorderedList, className: "bi bi-list-ul", title: "Generic List" },
        { name: "ordered-list", action: toggleOrderedList, className: "bi bi-list-ol", title: "Numbered List" }, "|",
        {
            name: "latex",
            action: (editor) => {
                const cm = editor.codemirror;
                const selection = cm.getSelection();
                if (selection) {
                    cm.replaceSelection(`$$ ${selection} $$`);
                } else {
                    const cursor = cm.getCursor();
                    cm.replaceRange("$$  $$", cursor);
                    cm.setCursor(cursor.line, cursor.ch + 3);
                }
                if (changeCallback) changeCallback();
            },
            className: "bi bi-plus-circle",
            title: "Insert LaTeX ($$)"
        },
        {
            name: "upload-image",
            className: "bi bi-upload",
            title: "Upload Image",
            action: (editor) => {
                const fileInput = document.createElement('input');
                fileInput.type = 'file';
                fileInput.accept = 'image/*';
                fileInput.style.display = 'none';
                document.body.appendChild(fileInput);

                fileInput.addEventListener('change', async function () {
                    if (!fileInput.files || !fileInput.files.length) {
                        fileInput.remove();
                        return;
                    }
                    const file = fileInput.files[0];
                    const formData = new FormData();
                    formData.append('image', file);

                    try {
                        const response = await fetch(MEDIA_UPLOAD_URL, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'Accept': 'application/json'
                            },
                            body: formData
                        });
                        const result = await response.json();
                        if (response.ok) {
                            const markdown = result.markdown || `![](${result.url})`;
                            const cm = editor.codemirror;
                            const cursor = cm.getCursor();
                            cm.replaceRange(`\n${markdown}\n`, cursor);
                            if (changeCallback) changeCallback();
                            showAlert('success', 'Image uploaded and inserted successfully');
                        } else {
                            showAlert('danger', result.message || 'Upload failed');
                        }
                    } catch (error) {
                        showAlert('danger', error.message);
                    }
                });

                fileInput.click();
            }
        }
        // No EasyMDE preview button on purpose. Its built-in preview renders
        // markdown with its own bundled parser, which eats the backslash in
        // \% and \$ and so disagrees with the live preview panel and the test
        // engine. The panel beside the editor is the single source of truth.
    ];
}

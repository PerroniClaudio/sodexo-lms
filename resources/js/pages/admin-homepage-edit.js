import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import Link from '@tiptap/extension-link';
import TextAlign from '@tiptap/extension-text-align';
import Underline from '@tiptap/extension-underline';

document.querySelectorAll('[data-tiptap-editor]').forEach((element) => {
    const target = document.getElementById(element.dataset.target);

    if (!target) {
        return;
    }

    const editor = new Editor({
        element,
        extensions: [
            StarterKit.configure({
                heading: {
                    levels: [1, 2, 3],
                },
            }),
            Underline,
            Link.configure({
                openOnClick: false,
                protocols: ['http', 'https', 'mailto', 'tel'],
            }),
            TextAlign.configure({
                types: ['heading', 'paragraph'],
            }),
        ],
        content: target.value,
        editorProps: {
            attributes: {
                class: 'min-h-44 focus:outline-none',
            },
        },
        onUpdate({ editor }) {
            target.value = editor.getHTML();
        },
    });

    document.querySelectorAll(`[data-tiptap-toolbar="${target.id}"] [data-command]`).forEach((button) => {
        button.addEventListener('click', () => {
            const command = button.dataset.command;
            const chain = editor.chain().focus();

            if (command === 'heading') {
                chain.toggleHeading({ level: Number(button.dataset.level) }).run();
                return;
            }

            if (command === 'paragraph') {
                chain.setParagraph().run();
                return;
            }

            if (command === 'undo') {
                chain.undo().run();
                return;
            }

            if (command === 'redo') {
                chain.redo().run();
                return;
            }

            if (command === 'bold') {
                chain.toggleBold().run();
                return;
            }

            if (command === 'italic') {
                chain.toggleItalic().run();
                return;
            }

            if (command === 'underline') {
                chain.toggleUnderline().run();
                return;
            }

            if (command === 'link') {
                const currentUrl = editor.getAttributes('link').href || '';
                const url = window.prompt('URL link', currentUrl);

                if (url === null) {
                    return;
                }

                if (url === '') {
                    chain.unsetLink().run();
                    return;
                }

                chain.extendMarkRange('link').setLink({ href: url }).run();
                return;
            }

            if (command === 'unsetLink') {
                chain.unsetLink().run();
                return;
            }

            if (command === 'bulletList') {
                chain.toggleBulletList().run();
                return;
            }

            if (command === 'orderedList') {
                chain.toggleOrderedList().run();
                return;
            }

            if (command === 'blockquote') {
                chain.toggleBlockquote().run();
                return;
            }

            if (command === 'codeBlock') {
                chain.toggleCodeBlock().run();
                return;
            }

            if (command === 'align') {
                chain.setTextAlign(button.dataset.align).run();
                return;
            }

            if (command === 'horizontalRule') {
                chain.setHorizontalRule().run();
            }
        });
    });
});

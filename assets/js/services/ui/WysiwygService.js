import $ from 'jquery'
import 'summernote/dist/summernote-bs5'
import 'summernote/dist/lang/summernote-fr-FR'

import '@/scss/lazy-components/_wysiwyg.scss'

function resolveElement(element) {
    if (typeof element === 'string') {
        return document.querySelector(element)
    }
    return element
}

export function create({
    element,
    lang = 'fr-FR',
    toolbar = [
        ['heading', ['style']],
        ['style', ['bold', 'italic', 'underline']],
        ['para', ['ul', 'ol', 'paragraph']],
        ['insert', ['link', 'picture', 'video', 'hr']],
        ['misc', ['fullscreen']],
    ],
    height = 280,
    codemirror = { mode: 'text/html', htmlMode: true },
} = {}) {
    const $el = $(resolveElement(element))

    $el.summernote({
        lang,
        toolbar,
        height,
        codemirror,
    })

    return {
        getCode: () => $el.summernote('code'),
        setCode: (html) => $el.summernote('code', html),
        isEmpty: () => $el.summernote('isEmpty'),
        destroy: () => $el.summernote('destroy'),
    }
}

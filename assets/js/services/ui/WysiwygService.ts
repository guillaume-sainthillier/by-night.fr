import $ from '@/js/jquery-global'
import 'summernote/dist/summernote-bs5'
import 'summernote/dist/lang/summernote-fr-FR'

import '@/scss/lazy-components/_wysiwyg.scss'

export interface WysiwygOptions {
    element: string | HTMLTextAreaElement
    lang?: string
    toolbar?: SummernoteToolbarGroup[]
    height?: number
    codemirror?: SummernoteOptions['codemirror']
}

export interface Wysiwyg {
    getCode: () => string
    setCode: (html: string) => void
    isEmpty: () => boolean
    destroy: () => void
}

function resolveElement(element: string | HTMLTextAreaElement): HTMLTextAreaElement | null {
    if (typeof element === 'string') {
        return document.querySelector<HTMLTextAreaElement>(element)
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
}: WysiwygOptions): Wysiwyg {
    const target = resolveElement(element)
    // No element: an empty collection, on which every call does nothing
    const $el = target ? $(target) : $()

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

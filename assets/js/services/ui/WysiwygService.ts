import { Jodit } from 'jodit/esm/index.js'
// Beyond the plugins jodit/esm brings (bold, lists, link, image, format block…)
import 'jodit/esm/plugins/fullsize/fullsize.js'
import 'jodit/esm/plugins/hr/hr.js'
import 'jodit/esm/plugins/justify/justify.js'
import 'jodit/esm/plugins/video/video.js'
import fr from 'jodit/esm/langs/fr.js'
import 'jodit/es2021/jodit.min.css'

import '@/scss/lazy-components/_wysiwyg.scss'

Jodit.lang.fr = fr

/** The toolbar: heading, emphasis, lists and alignment, then what can be inserted */
const BUTTONS = [
    'paragraph',
    '|',
    'bold',
    'italic',
    'underline',
    '|',
    'ul',
    'ol',
    'align',
    '|',
    'link',
    'image',
    'video',
    'hr',
    '|',
    'fullsize',
]

export interface WysiwygOptions {
    element: string | HTMLTextAreaElement
    height?: number
}

export interface Wysiwyg {
    getCode: () => string
    setCode: (html: string) => void
    destroy: () => void
}

function resolveElement(element: string | HTMLTextAreaElement): HTMLTextAreaElement | null {
    if (typeof element === 'string') {
        return document.querySelector<HTMLTextAreaElement>(element)
    }
    return element
}

/**
 * A rich text editor in place of a textarea, which it keeps up to date: the form sends its HTML, sanitized by the
 * server (HtmlFormatter).
 */
export function create({ element, height = 280 }: WysiwygOptions): Wysiwyg | null {
    const target = resolveElement(element)
    if (!target) {
        return null
    }

    const editor = Jodit.make(target, {
        language: 'fr',
        height,
        // The same toolbar on every width, wrapping on a phone, rather than buttons folded into a "…" menu
        toolbarAdaptive: false,
        buttons: BUTTONS,
        // Pictures go into the text, as summernote put them: there is no upload endpoint
        uploader: { insertImageAsBase64URI: true },
        // The server rebuilds every link as a nofollow one opening in a new tab, from its href alone
        // (HtmlFormatter::rewriteAnchors()): no fields for what it drops
        link: { modeClassName: undefined, openInNewTabCheckbox: false, noFollowCheckbox: false },
        // A paste keeps its formatting without asking: the server keeps what it allows
        askBeforePasteHTML: false,
        askBeforePasteFromWord: false,
        showCharsCounter: false,
        showWordsCounter: false,
        showXPathInStatusbar: false,
        hidePoweredByJodit: true,
    })

    return {
        getCode: () => editor.value,
        setCode: (html) => {
            editor.value = html
        },
        destroy: () => editor.destruct(),
    }
}

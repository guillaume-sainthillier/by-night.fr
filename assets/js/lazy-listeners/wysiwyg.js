import $ from 'jquery'
import 'summernote/dist/summernote-bs5'
import 'summernote/dist/lang/summernote-fr-FR'

import '../../scss/lazy-components/_wysiwyg.scss'

export default function init(container = document) {
    // SummerNote
    $('textarea.wysiwyg', container).summernote({
        lang: 'fr-FR',
        toolbar: [
            ['heading', ['style']],
            ['style', ['bold', 'italic', 'underline']],
            ['para', ['ul', 'ol', 'paragraph']],
            ['insert', ['link', 'picture', 'video', 'hr']],
            ['misc', ['fullscreen']],
        ],
        height: 280,
        codemirror: {
            mode: 'text/html',
            htmlMode: true,
        },
    })
}

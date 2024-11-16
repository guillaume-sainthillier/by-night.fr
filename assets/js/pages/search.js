import $ from 'jquery'
import initImagePreview from '@/js/lazy-listeners/image-previews'
import initSelects from '@/js/lazy-listeners/selects'

$(document).ready(function () {
    initImagePreview()
    initSelects()
})

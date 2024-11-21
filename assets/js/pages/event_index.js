import $ from 'jquery'
import initDates from '@/js/lazy-listeners/dates'
import initImagePreview from '@/js/lazy-listeners/image-previews'

$(document).ready(function () {
    initDates()
    initImagePreview()
})

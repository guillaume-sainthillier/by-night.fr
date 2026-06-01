import $ from 'jquery'
import { create as createFancybox } from '@/js/services/ui/FancyboxService'

$(document).ready(() => {
    document.querySelectorAll('.image-gallery').forEach((el) => {
        createFancybox({ element: el })
    })
})

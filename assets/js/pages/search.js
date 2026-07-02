import { create as createFancybox } from '@/js/services/ui/FancyboxService'

/** @type {Page} */
function initialize() {
    document.querySelectorAll('.image-gallery').forEach((el) => {
        createFancybox({ element: el })
    })
}

window.App.registerPage('search', initialize)

import $ from 'jquery'
import 'fancybox/dist/js/jquery.fancybox'
import 'fancybox/dist/css/jquery.fancybox.css'
import '@/scss/components/_image-previews.scss'

function resolveElement(element) {
    if (typeof element === 'string') {
        return document.querySelector(element)
    }
    return element
}

export function create({
    element,
    titlePosition = 'top',
    overlayLocked = false,
    preventClick = true,
} = {}) {
    const $el = $(resolveElement(element))

    $el.fancybox({
        helpers: {
            title: {
                type: 'inside',
                position: titlePosition,
            },
            overlay: {
                locked: overlayLocked,
            },
        },
    })

    if (preventClick) {
        $el.click(() => false)
    }

    return {
        open: () => $el.trigger('click'),
    }
}

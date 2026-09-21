import $ from '@/js/jquery-global'
import 'fancybox/dist/js/jquery.fancybox'
import 'fancybox/dist/css/jquery.fancybox.css'

function resolveElement(element) {
    if (typeof element === 'string') {
        return document.querySelector(element)
    }
    return element
}

export function create({ element, titlePosition = 'top', overlayLocked = false, preventClick = true } = {}) {
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
        // Our own namespace (lb = lightbox), so destroy() can unbind it without touching other click handlers
        $el.on('click.lb-prevent', () => false)
    }

    return {
        open: () => $el.trigger('click'),
        // `fb-start` is the plugin's own namespace for its opener (fb = FancyBox, not Facebook)
        destroy: () => $el.off('click.fb-start click.lb-prevent'),
    }
}

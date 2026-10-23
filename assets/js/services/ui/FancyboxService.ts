import $ from '@/js/jquery-global'
import 'fancybox/dist/js/jquery.fancybox'
import 'fancybox/dist/css/jquery.fancybox.css'

export interface FancyboxServiceOptions {
    element: string | HTMLElement
    titlePosition?: 'top' | 'bottom'
    overlayLocked?: boolean
    preventClick?: boolean
}

function resolveElement(element: string | HTMLElement): HTMLElement | null {
    if (typeof element === 'string') {
        return document.querySelector<HTMLElement>(element)
    }
    return element
}

export function create({
    element,
    titlePosition = 'top',
    overlayLocked = false,
    preventClick = true,
}: FancyboxServiceOptions): {
    open: () => void
    destroy: () => void
} {
    const target = resolveElement(element)
    // No element: an empty collection, on which every call does nothing
    const $el = target ? $(target) : $()

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

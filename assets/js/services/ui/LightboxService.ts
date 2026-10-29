import GLightbox from 'glightbox'
import 'glightbox/dist/css/glightbox.min.css'

export interface LightboxOptions {
    /** The link to the full-size image; its title is the caption */
    element: HTMLElement
}

/**
 * Opens a link's image in a lightbox (GLightbox) instead of following it. The lightbox is built on the first click.
 */
export function create({ element }: LightboxOptions): {
    open: () => void
    destroy: () => void
} {
    let lightbox: ReturnType<typeof GLightbox> | null = null

    const open = () => {
        // No selector: GLightbox binds nothing of the page by itself, and shows the one slide given
        lightbox ??= GLightbox({ selector: '', loop: false })
        lightbox.setElements([
            { href: element.getAttribute('href') ?? '', type: 'image', title: element.getAttribute('title') ?? '' },
        ])
        lightbox.open()
    }

    const onClick = (e: MouseEvent) => {
        e.preventDefault()
        open()
    }

    element.addEventListener('click', onClick)

    return {
        open,
        destroy: () => {
            element.removeEventListener('click', onClick)
            lightbox?.destroy()
        },
    }
}

import type { Listener } from '@silarhi/app-js'

/**
 * Click-to-load facade of the YouTube players of event descriptions: `HtmlFormatter::deferVideoPlayers()` renders a
 * placeholder carrying the player's youtube-nocookie.com address in `data-video-facade`, and the player only loads,
 * and starts, once the visitor clicks "Lire la vidéo YouTube".
 */
export default {
    selector: '[data-video-facade]',
    connect(element) {
        const button = element.querySelector('[data-video-facade-play]')
        if (!button) return undefined

        const onClick = () => {
            const src = new URL(element.dataset.videoFacade ?? '')
            src.searchParams.set('autoplay', '1')

            const frame = document.createElement('iframe')
            frame.src = src.toString()
            frame.title = 'Vidéo YouTube'
            frame.allow = 'autoplay; encrypted-media; picture-in-picture; fullscreen'
            frame.allowFullscreen = true
            frame.referrerPolicy = 'strict-origin-when-cross-origin'
            frame.style.border = '0'

            element.replaceChildren(frame)
        }

        button.addEventListener('click', onClick)

        return () => button.removeEventListener('click', onClick)
    },
} satisfies Listener<HTMLElement>

import type { Listener } from '@silarhi/app-js'

/**
 * A text the stylesheet cuts short on a phone (`[data-read-more]`, the copy of a location's first page): a "Lire la
 * suite" button under it shows the rest. Nothing where the whole text fits, on a larger screen.
 */
export default {
    selector: '[data-read-more]',
    connect(text) {
        if (text.scrollHeight <= text.clientHeight + 1) {
            return
        }

        const button = document.createElement('button')
        button.type = 'button'
        button.className = 'btn btn-link px-0'
        button.textContent = 'Lire la suite'
        const expand = () => {
            text.classList.add('is-expanded')
            button.remove()
        }
        button.addEventListener('click', expand)
        text.after(button)

        return () => button.remove()
    },
} satisfies Listener<HTMLElement>

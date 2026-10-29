import { defineListener } from '@silarhi/app-js'
import { popup } from '@/js/utils/utils'

/**
 * Open the link in a centered popup window.
 */
export default defineListener<HTMLAnchorElement>({
    selector: 'a.popup',
    connect(element) {
        const onClick = (e: MouseEvent) => {
            e.preventDefault()
            // Without an href, window.open() opens a blank page, as it does for an empty URL
            popup(element.getAttribute('href') ?? '', element)
        }

        element.addEventListener('click', onClick)

        return () => element.removeEventListener('click', onClick)
    },
})

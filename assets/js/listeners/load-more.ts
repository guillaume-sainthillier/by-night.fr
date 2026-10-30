import { defineListener } from '@silarhi/app-js'
import { getHtml } from '@silarhi/app-js/http'

/**
 * Load the next page of results and insert it before the button, then mount
 * the new content.
 */
export default defineListener<HTMLElement>({
    selector: '.more',
    connect(element, { app }) {
        const onClick = async (e: Event) => {
            e.preventDefault()

            element.setAttribute('disabled', 'disabled')
            const spinner = document.createElement('span')
            spinner.className = 'spinner-border spinner-border-sm'
            spinner.setAttribute('role', 'status')
            spinner.setAttribute('aria-hidden', 'true')
            element.prepend(spinner, ' ')

            const wrapper = element.parentElement
            const previousContainer = wrapper?.previousElementSibling
            let html: string
            try {
                // Without an href, the current page is read again, as jQuery did for an empty URL
                html = await getHtml(element.getAttribute('href') ?? '')
            } catch {
                // The spinner and the space after it
                spinner.nextSibling?.remove()
                spinner.remove()
                element.removeAttribute('disabled')
                app.get('toastManager').createToast('error', "La suite n'a pas pu être chargée, veuillez réessayer.")
                return
            }
            const currentContainer = document.createElement('div')
            currentContainer.innerHTML = html
            wrapper?.remove()
            previousContainer?.after(currentContainer)
            app.mount(currentContainer)
        }

        element.addEventListener('click', onClick)

        return () => element.removeEventListener('click', onClick)
    },
})

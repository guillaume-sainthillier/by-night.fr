import { defineListener } from '@silarhi/app-js'

/**
 * Submit the form designated by `data-target` when the element is clicked.
 */
export default defineListener<HTMLElement>({
    selector: 'input[data-target], button[data-target], a[data-target]',
    connect(elem) {
        const onClick = (e: MouseEvent) => {
            const targetSelector = elem.dataset.target ?? ''
            const target = document.querySelector(targetSelector)
            if (!(target instanceof HTMLFormElement)) {
                throw new Error(`No target available for ${targetSelector}`)
            }

            e.preventDefault()
            // Fires the submit event, which a confirmation (form-confirm.ts) may cancel, as a click on its button would
            target.requestSubmit()
        }

        elem.addEventListener('click', onClick)

        return () => elem.removeEventListener('click', onClick)
    },
})

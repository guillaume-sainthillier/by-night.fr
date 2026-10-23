import type { Listener } from '@silarhi/app-js'

const FEEDBACK_CLASS = 'file-max-size-feedback'

// Decimal units, as the File constraint counts them ("6M" is 6,000,000 bytes)
const megabytes = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 1 })

/**
 * Why the file can't be sent, or null when it can.
 *
 * @param maxSize in bytes
 */
export function oversizeMessage(file: { size: number; type?: string } | undefined, maxSize: number): string | null {
    if (!file || !(maxSize > 0) || file.size <= maxSize) {
        return null
    }

    const size = megabytes.format(file.size / 1e6)
    const max = megabytes.format(maxSize / 1e6)

    // A file that says it is no image (a spreadsheet to import): its own wording
    if (file.type && !file.type.startsWith('image/')) {
        return `Ce fichier pèse ${size} Mo, le maximum est de ${max} Mo. Découpez-le en plusieurs fichiers.`
    }

    return `Cette image pèse ${size} Mo, le maximum est de ${max} Mo. Réduisez-la ou choisissez-en une autre.`
}

/**
 * File fields carrying `data-max-size` (ImageFormats::MAX_SIZE): a heavier file is taken off the field as soon as it
 * is picked, with the reason under it. Sent, it went over PHP's post_max_size: the request reached the form empty
 * and the member lost everything filled in (BY-NIGHTFR-4MP).
 */
export default {
    selector: 'input[type="file"][data-max-size]',
    connect(input) {
        const feedback = document.createElement('div')
        feedback.className = `invalid-feedback d-block ${FEEDBACK_CLASS}`
        feedback.hidden = true
        input.after(feedback)

        const onChange = (e: Event) => {
            // The field emptied below: the reason stays shown
            if (!input.files?.length) {
                return
            }

            const message = oversizeMessage(input.files[0], Number(input.dataset.maxSize))
            feedback.textContent = message ?? ''
            feedback.hidden = null === message
            if (null === message) {
                return
            }

            // The other listeners never see the file: the dropzone would read it for its preview
            e.stopImmediatePropagation()
            input.value = ''
            input.dispatchEvent(new Event('change', { bubbles: true }))
        }

        // Captured: runs before the listeners of the field itself (the dropzone's)
        input.addEventListener('change', onChange, { capture: true })

        return () => {
            input.removeEventListener('change', onChange, { capture: true })
            feedback.remove()
        }
    },
} satisfies Listener<HTMLInputElement>

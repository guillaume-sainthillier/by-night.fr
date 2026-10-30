import { type App, definePage } from '@silarhi/app-js'
import { sendJson } from '@silarhi/app-js/http'

/**
 * Switches of the list of events: each sends its state when it changes, and is disabled until the answer came back.
 * A change the server did not take is undone.
 */
function bindSwitches(app: App, selector: string, body: (checkbox: HTMLInputElement) => object) {
    for (const checkbox of document.querySelectorAll<HTMLInputElement>(selector)) {
        checkbox.addEventListener('change', async () => {
            checkbox.disabled = true
            try {
                await sendJson('PUT', checkbox.dataset.href ?? '', body(checkbox))
            } catch {
                checkbox.checked = !checkbox.checked
                app.get('toastManager').createToast('error', "La modification n'a pas pu être enregistrée.")
            } finally {
                checkbox.disabled = false
            }
        })
    }
}

export default definePage(({ app }) => {
    bindSwitches(app, '.draft', (checkbox) => ({ draft: !checkbox.checked }))
    bindSwitches(app, '.cancel', (checkbox) => ({ cancel: checkbox.checked }))
})

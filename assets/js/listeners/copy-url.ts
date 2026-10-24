import { type App, defineListener } from '@silarhi/app-js'

/**
 * Copy an address to the clipboard and say so (or that the browser refused): the copy buttons, and the share buttons
 * without a share sheet (share.ts).
 */
export const copyLink = (app: App, url: string): void => {
    const toastManager = app.get('toastManager')
    navigator.clipboard.writeText(url).then(
        () => toastManager.createToast('success', 'Lien copié !'),
        () => toastManager.createToast('error', 'Impossible de copier le lien')
    )
}

/**
 * Copy the address a button carries in `data-copy-url` (the share menu of an event, the calendar of "Mes sorties").
 */
export default defineListener<HTMLButtonElement>({
    selector: '[data-copy-url]',
    connect(button, { app }) {
        const copy = () => copyLink(app, button.dataset.copyUrl ?? '')

        button.addEventListener('click', copy)

        return () => button.removeEventListener('click', copy)
    },
})

import type { App } from '@silarhi/app-js'
import { copyToClipboard } from '@silarhi/app-js/listeners'

/**
 * Copy an address to the clipboard and say so (or that the browser refused): the share buttons without a share sheet
 * (share.ts).
 */
export const copyLink = (app: App, url: string, message = 'Lien copié !'): void => {
    const toastManager = app.get('toastManager')
    navigator.clipboard.writeText(url).then(
        () => toastManager.createToast('success', message),
        () => toastManager.createToast('error', 'Impossible de copier le lien')
    )
}

/**
 * Copy the address a button carries in `data-copy` (the share menu of an event, the calendars), or the code of a
 * widget, saying so with its `data-copy-message` when it is not a link.
 */
export default copyToClipboard({
    selector: '[data-copy]',
    onSuccess: (button, _text, { app }) =>
        app.get('toastManager').createToast('success', button.dataset.copyMessage ?? 'Lien copié !'),
    onError: (_button, _error, { app }) => app.get('toastManager').createToast('error', 'Impossible de copier le lien'),
})

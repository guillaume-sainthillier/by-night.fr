/**
 * Copy the address a button carries in `data-copy-url` (the share menu of an event, the calendar of "Mes sorties").
 *
 * @type {Listener}
 */
export default {
    selector: '[data-copy-url]',
    connect(button, { app }) {
        const copy = () => {
            const toastManager = app.get('toastManager')
            navigator.clipboard.writeText(button.dataset.copyUrl).then(
                () => toastManager.createToast('success', 'Lien copié !'),
                () => toastManager.createToast('error', 'Impossible de copier le lien')
            )
        }

        button.addEventListener('click', copy)

        return () => button.removeEventListener('click', copy)
    },
}

import { followTransfers } from '@/js/utils/transfers'

/** The toast a finished job deserves, by its status: [icon, message], none for the others */
const IMPORT_TOASTS = {
    checked: ['success', 'Votre fichier est vérifié : le bilan vous attend.'],
    check_failed: ['error', 'Votre fichier n’a pas pu être vérifié.'],
    completed: ['success', 'Votre import est terminé.'],
    failed: ['warning', 'Votre import s’est interrompu : vous pouvez le reprendre.'],
}

const EXPORT_TOASTS = {
    completed: ['success', 'Votre export est prêt à télécharger.'],
    failed: ['error', 'Votre export n’a pas pu être écrit.'],
}

/**
 * What to say of a job's update: its icon, message and the page to see it on, or null when there is nothing to say
 * (still running, cancelled).
 *
 * @param {{'@id': string, status: string}} state the job's state (TransferNotifier)
 * @param {string} exportsUrl the export page, where an export is downloaded
 * @returns {{icon: string, message: string, url: string}|null}
 */
export function transferToast(state, exportsUrl) {
    const isExport = /\/exports\/\d+$/.test(state['@id'])
    const toast = (isExport ? EXPORT_TOASTS : IMPORT_TOASTS)[state.status]
    if (!toast) {
        return null
    }

    return { icon: toast[0], message: toast[1], url: isExport ? exportsUrl : state['@id'] }
}

/**
 * On every page of the personal space (`[data-transfer-toasts]`, the hub's URL on the member's channel): a toast
 * when an import or an export of theirs is done, linking to it. Not on the import / export pages, which show it.
 *
 * @type {Listener}
 */
export default {
    selector: '[data-transfer-toasts]',
    connect(element, { app }) {
        if (document.querySelector('[data-transfer-refresh]')) {
            return undefined
        }

        // One toast per job and status: updates repeat the whole state
        const told = new Set()
        const exportsUrl = new URL('/espace-perso/exports', window.location.origin).href

        return followTransfers({
            hub: element.dataset.transferToasts,
            onChange: (state) => {
                const toast = state && transferToast(state, exportsUrl)
                const key = state && `${state['@id']}|${state.status}`
                if (!toast || told.has(key)) {
                    return
                }

                told.add(key)
                const link = document.createElement('a')
                link.href = toast.url
                link.textContent = 'Voir'
                app.get('toastManager').createToast(toast.icon, toast.message, { html: link.outerHTML, timer: 10000 })
            },
        })
    },
}

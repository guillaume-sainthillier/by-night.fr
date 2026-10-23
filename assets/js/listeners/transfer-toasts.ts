import type { Listener } from '@silarhi/app-js'
import type { SweetAlertIcon } from 'sweetalert2'
import { followTransfers, type TransferState } from '@/js/utils/transfers'

type ToastsByStatus = Partial<Record<string, [SweetAlertIcon, string]>>

/** The toast a finished job deserves, by its status: [icon, message], none for the others */
const IMPORT_TOASTS: ToastsByStatus = {
    checked: ['success', 'Votre fichier est vérifié : le bilan vous attend.'],
    check_failed: ['error', 'Votre fichier n’a pas pu être vérifié.'],
    completed: ['success', 'Votre import est terminé.'],
    failed: ['warning', 'Votre import s’est interrompu : vous pouvez le reprendre.'],
}

const EXPORT_TOASTS: ToastsByStatus = {
    completed: ['success', 'Votre export est prêt à télécharger.'],
    failed: ['error', 'Votre export n’a pas pu être écrit.'],
}

/** What a toast reads of a job's state */
type ToastedState = Pick<TransferState, '@id' | 'status'>

interface TransferToast {
    icon: SweetAlertIcon
    message: string
    url: string
}

/**
 * What to say of a job's update: its icon, message and the page to see it on, or null when there is nothing to say
 * (still running, cancelled).
 *
 * @param state the job's state (TransferNotifier). Its IRI is on the pages of its publisher: an import's report
 *   (…/imports/{id}), or an export (…/exports/{id}), downloaded from the export page. Read by path: the worker writes
 *   the IRIs with APP_URL, which may not be the host the page is read on
 */
export function transferToast(state: ToastedState): TransferToast | null {
    const path = new URL(state['@id'], 'https://localhost').pathname
    const exportsPath = path.match(/^(.*\/exports)\/\d+$/)?.[1]
    const toast = (exportsPath ? EXPORT_TOASTS : IMPORT_TOASTS)[state.status]
    if (!toast) {
        return null
    }

    return { icon: toast[0], message: toast[1], url: exportsPath ?? path }
}

/**
 * On every page of the personal space (`[data-transfer-toasts]`, the hub's URL on the member's channel): a toast when
 * an import or an export of theirs is done, whatever their publisher, linking to it. Not on the import / export pages,
 * which show it.
 */
export default {
    selector: '[data-transfer-toasts]',
    connect(element, { app }) {
        if (document.querySelector('[data-transfer-refresh]')) {
            return undefined
        }

        // One toast per job and status: updates repeat the whole state
        const told = new Set<string>()
        const hub = element.dataset.transferToasts
        // Written by the template
        if (!hub) {
            return undefined
        }

        return followTransfers({
            hub,
            onChange: (state: TransferState | null) => {
                if (!state) {
                    return
                }

                const toast = transferToast(state)
                const key = `${state['@id']}|${state.status}`
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
} satisfies Listener<HTMLElement>

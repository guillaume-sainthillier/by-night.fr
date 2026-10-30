import { getHtml, statusOf } from '@silarhi/app-js/http'
import { Modal } from '@tabler/core'

/**
 * The message the shared dialog shows when its content cannot be loaded.
 *
 * @param status the HTTP status of the response, 0 when there was none
 */
export function loadErrorMessage(status: number): string {
    if (0 === status) {
        return 'La connexion a échoué, veuillez réessayer.'
    }

    return `Le contenu n'a pas pu être chargé (erreur ${status}), veuillez réessayer.`
}

/**
 * Loads a page into the shared dialog (#dialog_details, showing its spinner), then runs `onLoaded`. A page that cannot
 * be loaded replaces the spinner with an error.
 */
export async function loadIntoDialog(dialog: HTMLElement, url: string, onLoaded: () => void): Promise<void> {
    let html: string
    try {
        html = await getHtml(url)
    } catch (error) {
        const modal = Modal.getOrCreateInstance(dialog)
        if (modal instanceof Modal) {
            modal.setError(loadErrorMessage(statusOf(error)))
        }

        return
    }

    dialog.innerHTML = html
    onLoaded()
}

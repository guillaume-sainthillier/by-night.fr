/**
 * Before an event goes online, asks for the events already online it likely repeats (EventController::renderDuplicates())
 * and, if there are some, lets the member confirm. The check only advises: whatever keeps it from answering (a network
 * failure, an error, a lapsed session redirected to the login page) publishes as before.
 *
 * @param {{createConfirm: (params: object) => Promise<boolean|undefined>}} modalManager
 * @param {string} url
 * @param {RequestInit} [init] the event's form for a POST, none to search the draft as saved
 * @returns {Promise<boolean>} whether to publish
 */
export async function confirmPublication(modalManager, url, init = {}) {
    let response
    try {
        response = await fetch(url, { ...init, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
    } catch {
        return true
    }

    // 204: nothing alike
    if (200 !== response.status || response.redirected) {
        return true
    }

    const confirmed = await modalManager.createConfirm({
        icon: 'info',
        title: 'Cet événement est peut-être déjà en ligne',
        html: await response.text(),
        width: 640,
        confirmButtonText: 'Publier quand même',
        cancelButtonText: 'Annuler',
    })

    return true === confirmed
}

/**
 * The fields of a form to search its likely duplicates: the picture left out, which the search does not read and the
 * request would upload for nothing.
 *
 * @param {HTMLFormElement} form
 * @returns {FormData}
 */
export function duplicatesSearchBody(form) {
    const body = new FormData(form)
    for (const [name, value] of [...body.entries()]) {
        if (value instanceof File) {
            body.delete(name)
        }
    }

    return body
}

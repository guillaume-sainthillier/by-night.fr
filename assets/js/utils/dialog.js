/**
 * The message the shared dialog shows when its content cannot be loaded.
 *
 * @param {number} status the HTTP status of the response, 0 when there was none
 * @returns {string}
 */
export function loadErrorMessage(status) {
    if (0 === status) {
        return 'La connexion a échoué, veuillez réessayer.'
    }

    return `Le contenu n'a pas pu être chargé (erreur ${status}), veuillez réessayer.`
}

/**
 * Loads a page into the shared dialog (#dialog_details, showing its spinner), then runs
 * `onLoaded`. jQuery's load() only inserts a successful response: on an error the dialog kept
 * its spinner for good, and the global error handler meant to replace it never ran.
 *
 * @param {JQuery} $dialog
 * @param {string} url
 * @param {() => void} onLoaded
 */
export function loadIntoDialog($dialog, url, onLoaded) {
    $dialog.load(url, (_response, status, xhr) => {
        if ('error' === status) {
            $dialog.modal('setError', loadErrorMessage(xhr.status))

            return
        }

        onLoaded()
    })
}

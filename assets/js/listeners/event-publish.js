/**
 * Puts the event online through the draft API, returning whether it worked.
 *
 * @param {string} url
 * @returns {Promise<boolean>}
 */
async function publish(url) {
    const response = await fetch(url, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({ draft: false }),
    })

    // A lapsed session is redirected to the login page, whose HTML makes json() throw
    return response.ok && (await response.json()).success === true
}

/**
 * "Publish" button of a draft's preview: publishes the event, then drops the preview notice.
 *
 * @type {Listener}
 */
export default {
    selector: 'button[data-publish-href]',
    connect(button, { app }) {
        const onClick = async () => {
            button.disabled = true
            const published = await publish(button.dataset.publishHref).catch(() => false)
            if (!published) {
                button.disabled = false
                app.get('toastManager').createToast('error', "L'événement n'a pas pu être publié, veuillez réessayer.")
                return
            }

            app.get('toastManager').createToast('success', "L'événement est en ligne.")
            button.closest('.alert')?.remove()
        }

        button.addEventListener('click', onClick)

        return () => button.removeEventListener('click', onClick)
    },
}

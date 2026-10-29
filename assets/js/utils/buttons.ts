/**
 * Disables the submit buttons of `container` and shows a spinner in them while a form is sent.
 */
export const loadingButtons = (container: ParentNode) => {
    for (const button of container.querySelectorAll<HTMLButtonElement>('.btn-submit')) {
        button.disabled = true
        button.insertAdjacentHTML(
            'afterbegin',
            '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>'
        )
    }
}

/**
 * Undoes `loadingButtons()`.
 */
export const resetButtons = (container: ParentNode) => {
    for (const button of container.querySelectorAll<HTMLButtonElement>('.btn-submit')) {
        button.disabled = false
        button.querySelector('.spinner-border')?.remove()
    }
}

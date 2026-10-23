import $ from 'jquery'

/**
 * Disables the submit buttons of `container` and shows a spinner in them while a form is sent.
 */
export const loadingButtons = (container: JQuery.Selector | Element | JQuery) => {
    $('.btn-submit', container)
        .prop('disabled', true)
        .prepend('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>')
}

/**
 * Undoes `loadingButtons()`.
 */
export const resetButtons = (container: JQuery.Selector | Element | JQuery) => {
    $('.btn-submit', container).prop('disabled', false).find('.spinner-border').remove()
}

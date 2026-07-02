import $ from 'jquery'

/**
 * Activate a Bootstrap tooltip on each tooltip-toggle element. Disposes it on
 * disconnect.
 *
 * @type {Listener}
 */
export default {
    selector: '[data-bs-toggle="tooltip"]',
    connect(element) {
        const $element = $(element)
        $element.tooltip()

        return () => $element.tooltip('dispose')
    },
}

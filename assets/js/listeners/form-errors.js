import $ from 'jquery'

/**
 * Keep static material labels positioned when their field has errors.
 *
 * @type {Listener}
 */
export default {
    selector: 'label.bmd-label-static',
    connect(element) {
        $(element).toggleClass('position-static', $(element).find('.invalid-feedback').length > 0)
    },
}

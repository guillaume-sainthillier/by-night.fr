import $ from 'jquery'
import { popup } from '@/js/utils/utils'

/**
 * Open the link in a centered popup window.
 *
 * @type {Listener}
 */
export default {
    selector: 'a.popup',
    connect(element) {
        const $element = $(element)

        $element.on('click.popup', function () {
            popup($(this).attr('href'), this)
            return false
        })

        return () => $element.off('.popup')
    },
}

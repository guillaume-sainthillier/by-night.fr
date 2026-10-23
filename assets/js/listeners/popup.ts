import type { Listener } from '@silarhi/app-js'
import $ from 'jquery'
import { popup } from '@/js/utils/utils'

/**
 * Open the link in a centered popup window.
 */
export default {
    selector: 'a.popup',
    connect(element) {
        const $element = $(element)

        $element.on('click.popup', function () {
            // Without an href, window.open() opens a blank page, as it does for an empty URL
            popup($(this).attr('href') ?? '', this)
            return false
        })

        return () => $element.off('.popup')
    },
} satisfies Listener<HTMLAnchorElement>

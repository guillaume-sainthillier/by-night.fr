import { defineListener } from '@silarhi/app-js'
import $ from 'jquery'

/**
 * Keep static material labels positioned when their field has errors.
 */
export default defineListener<HTMLLabelElement>({
    selector: 'label.bmd-label-static',
    connect(element) {
        $(element).toggleClass('position-static', $(element).find('.invalid-feedback').length > 0)

        return undefined
    },
})

import { defineListener } from '@silarhi/app-js'

/**
 * Keep static material labels positioned when their field has errors.
 */
export default defineListener<HTMLLabelElement>({
    selector: 'label.bmd-label-static',
    connect(element) {
        element.classList.toggle('position-static', null !== element.querySelector('.invalid-feedback'))

        return undefined
    },
})

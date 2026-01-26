import TomSelect from 'tom-select'
import '@/scss/lazy-components/_selects.scss'
import { isTouchDevice } from '@/js/utils/utils'

export default (container = document) => {
    container.querySelectorAll('select.form-select:not(.hidden):not(.tomselected)').forEach((el) => {
        if (isTouchDevice()) {
            el.setAttribute('size', el.getAttribute('size') || 1)
        } else {
            new TomSelect(el, {
                create: false,
                controlInput: null,
                allowEmptyOption: true,
                render: {
                    no_results() {
                        return '<div class="no-results">Aucun r\u00e9sultat</div>'
                    },
                },
            })
        }
    })
}

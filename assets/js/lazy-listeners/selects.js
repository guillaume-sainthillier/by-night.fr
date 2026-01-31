import $ from 'jquery'
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
                plugins: ['remove_button'],
                allowEmptyOption: true,
                render: {
                    no_results() {
                        return '<div class="no-results">Aucun résultat</div>'
                    },
                },
            })
            initRefreshableSelects(el)
        }
    })
}

export function initRefreshableSelects(element) {
    $(element).on('refresh', function() {
        const instance = element.tomselect
        instance?.setValue($(element).val(), true)
    })
}

import $ from 'jquery'
import TomSelect from 'tom-select'
import { isTouchDevice } from '@/js/utils/utils'

import '@/scss/lazy-components/_selects.scss'

function resolveElement(element) {
    if (typeof element === 'string') {
        return document.querySelector(element)
    }
    return element
}

export function create({
    element,
    create: allowCreate = false,
    plugins = ['remove_button'],
    allowEmptyOption = true,
    noResultsText = 'Aucun résultat',
    ...tomSelectOptions
} = {}) {
    const el = resolveElement(element)

    // On touch devices, use native select for better UX
    if (isTouchDevice()) {
        el.setAttribute('size', el.getAttribute('size') || 1)
        return null
    }

    const instance = new TomSelect(el, {
        create: allowCreate,
        plugins,
        allowEmptyOption,
        render: {
            no_results: () => `<div class="no-results">${noResultsText}</div>`,
        },
        ...tomSelectOptions,
    })

    // Setup refresh event listener
    $(el).on('refresh', function () {
        instance.setValue($(el).val(), true)
    })

    return {
        setValue: (value, silent) => instance.setValue(value, silent),
        getValue: () => instance.getValue(),
        addOption: (data) => instance.addOption(data),
        clearOptions: () => instance.clearOptions(),
        clear: () => instance.clear(),
        destroy: () => instance.destroy(),
    }
}

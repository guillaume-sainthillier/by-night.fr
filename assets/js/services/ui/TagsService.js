import TomSelect from 'tom-select'

import '@/scss/lazy-components/_selects.scss'
import '@/scss/lazy-components/_tags.scss'

function resolveElement(element) {
    if (typeof element === 'string') {
        return document.querySelector(element)
    }
    return element
}

export function create({
    element,
    url = null,
    allowNew = false,
    maxItems = null,
    separator = ',',
    placeholder = '',
    plugins = ['remove_button'],
    noResultsText = 'Aucun résultat',
    valueField = 'id',
    labelField = 'text',
    fetchOptions = { headers: { Accept: 'application/ld+json' } },
    transformResponse = (data) => data['hydra:member'] || data.member || data,
} = {}) {
    const el = resolveElement(element)

    const options = {
        delimiter: separator,
        persist: false,
        create: allowNew,
        maxItems,
        placeholder,
        plugins,
        render: {
            no_results: () => `<div class="no-results">${noResultsText}</div>`,
        },
    }

    if (url) {
        options.valueField = valueField
        options.labelField = labelField
        options.searchField = []
        options.sortField = [{field:'$order'},{field:'$score'}]
        options.load = (query, callback) => {
            fetch(`${url}?q=${encodeURIComponent(query)}`, fetchOptions)
                .then((res) => res.json())
                .then((data) => callback(transformResponse(data)))
                .catch(() => callback())
        }
    }

    const instance = new TomSelect(el, options)

    return {
        addItem: (value) => instance.addItem(value),
        removeItem: (value) => instance.removeItem(value),
        clear: () => instance.clear(),
        destroy: () => instance.destroy(),
    }
}

import TomSelect from 'tom-select'
import * as fr from '@/js/services/ui/tomSelectFr'

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
    plugins = fr.plugins,
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
        // A new tag can be added: no "no results" under the "Ajouter …" option
        render: allowNew ? { ...fr.render, no_results: null } : fr.render,
    }

    if (url) {
        options.valueField = valueField
        options.labelField = labelField
        options.searchField = []
        options.sortField = [{ field: '$order' }, { field: '$score' }]

        // The server already filtered the hits, but tom-select keeps every option earlier searches loaded: only
        // those of the typed text are listed, none while its results are on their way
        let loadedQuery = null
        options.score = (query) => () => (query === loadedQuery ? 1 : 0)
        options.load = function (query, callback) {
            const fetchUrl = url.replace('__QUERY__', encodeURIComponent(query))
            fetch(fetchUrl, fetchOptions)
                .then((res) => res.json())
                .then((data) => {
                    // A slower answer to text typed since
                    if (query !== this.lastValue) {
                        callback()
                        return
                    }
                    loadedQuery = query
                    // Keeps the selected options; addOption() would not update an option loaded before
                    this.clearOptions()
                    callback(transformResponse(data))
                })
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

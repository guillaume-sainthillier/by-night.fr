import TomSelect from 'tom-select'
import type { TomLoadCallback } from 'tom-select/dist/esm/types/core.js'
import * as fr from '@/js/services/ui/tomSelectFr'

import '@/scss/lazy-components/_selects.scss'
import '@/scss/lazy-components/_tags.scss'

type TomSelectSettings = ConstructorParameters<typeof TomSelect>[1]

/** A tag the API returns: the fields named by `valueField` and `labelField` among others */
type TagRecord = Record<string, unknown>

export interface TagsOptions {
    element: string | HTMLInputElement | HTMLSelectElement
    url?: string | null
    allowNew?: boolean
    maxItems?: number | null
    separator?: string
    placeholder?: string
    plugins?: TomSelectSettings['plugins']
    valueField?: string
    labelField?: string
    fetchOptions?: RequestInit
    transformResponse?: (data: unknown) => TagRecord[]
}

export interface Tags {
    addItem: (value: string) => void
    removeItem: (value: string) => void
    clear: () => void
    destroy: () => void
}

function resolveElement(
    element: string | HTMLInputElement | HTMLSelectElement
): HTMLInputElement | HTMLSelectElement | null {
    if (typeof element === 'string') {
        return document.querySelector<HTMLInputElement | HTMLSelectElement>(element)
    }
    return element
}

// An API Platform collection (JSON-LD, older "hydra:" prefix or not), else the list itself
function members(data: unknown): TagRecord[] {
    const collection = data as { 'hydra:member'?: TagRecord[]; member?: TagRecord[] }
    return collection['hydra:member'] || collection.member || (data as TagRecord[])
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
    transformResponse = members,
}: TagsOptions): Tags {
    const el = resolveElement(element)
    if (!el) {
        throw new Error(`Tags: no field matches ${String(element)}`)
    }

    const options: TomSelectSettings = {
        delimiter: separator,
        persist: false,
        create: allowNew,
        maxItems,
        placeholder,
        plugins,
        // A new tag can be added: no "no results" under the "Ajouter …" option (tom-select renders no
        // template that is not a function)
        render: allowNew ? { ...fr.render, no_results: undefined } : fr.render,
    }

    if (url) {
        options.valueField = valueField
        options.labelField = labelField
        options.searchField = []
        options.sortField = [{ field: '$order' }, { field: '$score' }]

        // The server already filtered the hits, but tom-select keeps every option earlier searches loaded: only
        // those of the typed text are listed, none while its results are on their way
        let loadedQuery: string | null = null
        options.score = (query: string) => () => (query === loadedQuery ? 1 : 0)
        options.load = function (this: TomSelect, query: string, callback: TomLoadCallback) {
            const fetchUrl = url.replace('__QUERY__', encodeURIComponent(query))
            fetch(fetchUrl, fetchOptions)
                .then((res) => res.json())
                .then((data: unknown) => {
                    // A slower answer to text typed since
                    if (query !== this.lastValue) {
                        callback([], [])
                        return
                    }
                    loadedQuery = query
                    // Keeps the selected options; addOption() would not update an option loaded before
                    this.clearOptions()
                    callback(transformResponse(data), [])
                })
                .catch(() => callback([], []))
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

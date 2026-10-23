import autoComplete from '@tarekraafat/autocomplete.js'
import { highlightMatch } from '@/js/utils/highlight'

/** A result of the API: the fields named by `labelField` and `valueField` among others */
export type AutocompleteRecord = Record<string, unknown>

export interface AutocompleteOptions<T extends AutocompleteRecord> {
    element: string | HTMLInputElement
    url: string
    valueField?: string
    labelField?: string
    valueInput?: string | HTMLInputElement | null
    minLength?: number
    throttle?: number
    maxResults?: number
    highlight?: boolean
    noResults?: boolean
    noResultsText?: string
    errorText?: string
    placeholder?: string | null
    onSelection?: ((selection: T) => void) | null
    onInput?: ((value: string) => void) | null
    onError?: ((error: unknown) => void) | null
    /** Wraps the input in a div of its own (the library's default); false leaves it in place */
    wrapper?: boolean | null
    inModal?: boolean
    transformResponse?: (data: unknown) => T[]
    fetchOptions?: RequestInit
}

export interface Autocomplete {
    start: () => void
    destroy: () => void
}

function resolveElement(element: string | HTMLInputElement): HTMLInputElement | null {
    if (typeof element === 'string') {
        return document.querySelector<HTMLInputElement>(element)
    }
    return element
}

// An API Platform collection (JSON-LD, older "hydra:" prefix or not), else the list itself
function members<T>(data: unknown): T[] {
    const collection = data as { 'hydra:member'?: T[]; member?: T[] }
    return collection['hydra:member'] || collection.member || (data as T[])
}

export function create<T extends AutocompleteRecord = AutocompleteRecord>({
    element,
    url,
    valueField = 'slug',
    labelField = 'name',
    valueInput = null,
    minLength = 1,
    throttle = 200,
    maxResults = 10,
    highlight = true,
    noResults = true,
    noResultsText = 'Aucun résultat',
    errorText = 'Une erreur est survenue',
    placeholder = null,
    onSelection = null,
    onInput = null,
    onError = null,
    wrapper = null,
    inModal = false,
    transformResponse = members,
    fetchOptions = { headers: { Accept: 'application/ld+json' } },
}: AutocompleteOptions<T>): Autocomplete {
    const inputElement = resolveElement(element)
    if (!inputElement) {
        throw new Error(`Autocomplete: no input matches ${String(element)}`)
    }
    const valueInputElement = valueInput ? resolveElement(valueInput) : null

    if (placeholder) {
        inputElement.placeholder = placeholder
    }

    // Disable browser autocomplete to avoid conflicts
    inputElement.setAttribute('autocomplete', 'off')

    let fetchError: unknown = null

    const ac = new autoComplete<T>({
        selector: () => inputElement,
        wrapper,
        data: {
            src: async (query) => {
                fetchError = null
                const fetchUrl = url.replace('__QUERY__', encodeURIComponent(query))
                try {
                    const res = await fetch(fetchUrl, fetchOptions)
                    if (!res.ok) {
                        throw new Error(`HTTP ${res.status}`)
                    }
                    const data: unknown = await res.json()
                    return transformResponse(data)
                } catch (error) {
                    fetchError = error
                    if (onError) {
                        onError(error)
                    }
                    return []
                }
            },
            keys: [labelField],
        },
        threshold: minLength,
        debounce: throttle,
        resultsList: {
            noResults,
            maxResults,
            element: (list, data) => {
                if (data.results.length === 0) {
                    const message = document.createElement('li')
                    if (fetchError) {
                        message.className = 'no_result error'
                        message.textContent = errorText
                    } else if (noResults) {
                        message.className = 'no_result'
                        message.textContent = noResultsText
                    } else {
                        return
                    }
                    list.appendChild(message)
                }
            },
        },
        // The library renders each match as HTML: this engine escapes the record before marking the match
        searchEngine: (query, record) => highlightMatch(query, record, highlight),
        events: {
            input: {
                selection: (event) => {
                    const selection = event.detail.selection.value
                    // The "no result" and error messages are list items the library selects as an empty result
                    if (!selection) {
                        return
                    }

                    ac.input.value = String(selection[labelField])

                    if (valueInputElement) {
                        valueInputElement.value = String(selection[valueField])
                    }

                    if (onSelection) {
                        onSelection(selection)
                    }
                },
            },
        },
    })

    if (inModal && wrapper) {
        inputElement.parentElement?.before(inputElement)
    }

    // Clear value input when user edits the text
    if (valueInputElement) {
        inputElement.addEventListener('input', () => {
            valueInputElement.value = ''
            if (onInput) {
                onInput(inputElement.value)
            }
        })
    } else if (onInput) {
        inputElement.addEventListener('input', () => {
            onInput(inputElement.value)
        })
    }

    return {
        start: () => ac.start(),
        destroy: () => ac.unInit(),
    }
}

import autoComplete from '@tarekraafat/autocomplete.js'

/**
 * @typedef {Object} AutocompleteOptions
 * @property {HTMLInputElement|string} element - Input element or CSS selector
 * @property {string} url - API endpoint URL (use %QUERY as placeholder for search term)
 * @property {string} [valueField='slug'] - Field name for the value to store
 * @property {string} [labelField='name'] - Field name for the display label
 * @property {HTMLInputElement|string} [valueInput] - Hidden input to store the selected value
 * @property {number} [minLength=1] - Minimum characters before searching
 * @property {number} [throttle=200] - Throttle delay in ms between API calls
 * @property {number} [maxResults=10] - Maximum results to display
 * @property {boolean} [highlight=true] - Highlight matching text in results
 * @property {boolean} [noResults=true] - Show "no results" message
 * @property {string} [noResultsText='Aucun résultat'] - Text for no results
 * @property {string} [errorText='Une erreur est survenue'] - Text for fetch errors
 * @property {string} [placeholder] - Input placeholder text
 * @property {Function} [onSelection] - Callback when an item is selected (receives selection object)
 * @property {Function} [onInput] - Callback when input changes (receives input value)
 * @property {Function} [onError] - Callback when fetch fails (receives error)
 * @property {Function} [transformResponse] - Transform API response to array of items
 * @property {Object} [fetchOptions] - Additional fetch options (headers, etc.)
 */

export default class AutocompleteService {
    /** @type {Map<HTMLElement, autoComplete>} */
    #instances = new Map()

    /**
     * Default options for all autocomplete instances
     */
    #defaults = {
        valueField: 'slug',
        labelField: 'name',
        minLength: 1,
        throttle: 200,
        maxResults: 10,
        highlight: true,
        noResults: true,
        noResultsText: 'Aucun résultat',
        errorText: 'Une erreur est survenue',
        fetchOptions: {
            headers: { Accept: 'application/ld+json' },
        },
        transformResponse: (data) => data['hydra:member'] || data.member || data,
    }

    /**
     * Create a new autocomplete instance
     * @param {AutocompleteOptions} options
     * @returns {autoComplete}
     */
    create(options) {
        const config = { ...this.#defaults, ...options }
        const inputElement = this.#resolveElement(config.element)
        const valueInput = config.valueInput ? this.#resolveElement(config.valueInput) : null

        if (config.placeholder) {
            inputElement.placeholder = config.placeholder
        }

        // Disable browser autocomplete to avoid conflicts
        inputElement.setAttribute('autocomplete', 'off')

        let fetchError = null

        const ac = new autoComplete({
            selector: () => inputElement,
            data: {
                src: async (query) => {
                    fetchError = null
                    const url = config.url.replace('%QUERY', encodeURIComponent(query))
                    try {
                        const res = await fetch(url, config.fetchOptions)
                        if (!res.ok) {
                            throw new Error(`HTTP ${res.status}`)
                        }
                        const data = await res.json()
                        return config.transformResponse(data)
                    } catch (error) {
                        fetchError = error
                        if (config.onError) {
                            config.onError(error, ac)
                        }
                        return []
                    }
                },
                keys: [config.labelField],
            },
            threshold: config.minLength,
            debounce: config.throttle,
            resultsList: {
                noResults: config.noResults,
                maxResults: config.maxResults,
                element: (list, data) => {
                    if (data.results.length === 0) {
                        const message = document.createElement('li')
                        if (fetchError) {
                            message.className = 'no_result error'
                            message.textContent = config.errorText
                        } else if (config.noResults) {
                            message.className = 'no_result'
                            message.textContent = config.noResultsText
                        } else {
                            return
                        }
                        list.appendChild(message)
                    }
                },
            },
            resultItem: {
                highlight: config.highlight,
            },
            events: {
                input: {
                    selection: (event) => {
                        const selection = event.detail.selection.value
                        ac.input.value = selection[config.labelField]

                        if (valueInput) {
                            valueInput.value = selection[config.valueField]
                        }

                        if (config.onSelection) {
                            config.onSelection(selection, ac)
                        }
                    },
                },
            },
        })

        // Clear value input when user edits the text
        if (valueInput) {
            inputElement.addEventListener('input', () => {
                valueInput.value = ''
                if (config.onInput) {
                    config.onInput(inputElement.value, ac)
                }
            })
        } else if (config.onInput) {
            inputElement.addEventListener('input', () => {
                config.onInput(inputElement.value, ac)
            })
        }

        this.#instances.set(inputElement, ac)
        return ac
    }

    /**
     * Get an existing autocomplete instance by its input element
     * @param {HTMLInputElement|string} element
     * @returns {autoComplete|undefined}
     */
    get(element) {
        const el = this.#resolveElement(element)
        return this.#instances.get(el)
    }

    /**
     * Destroy an autocomplete instance
     * @param {HTMLInputElement|string} element
     */
    destroy(element) {
        const el = this.#resolveElement(element)
        const instance = this.#instances.get(el)
        if (instance) {
            instance.unInit()
            this.#instances.delete(el)
        }
    }

    /**
     * @param {HTMLInputElement|string} element
     * @returns {HTMLInputElement}
     */
    #resolveElement(element) {
        if (typeof element === 'string') {
            return document.querySelector(element)
        }
        return element
    }
}

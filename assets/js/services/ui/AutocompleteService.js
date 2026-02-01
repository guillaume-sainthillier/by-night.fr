import autoComplete from '@tarekraafat/autocomplete.js'

function resolveElement(element) {
    if (typeof element === 'string') {
        return document.querySelector(element)
    }
    return element
}

export function create({
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
    transformResponse = (data) => data['hydra:member'] || data.member || data,
    fetchOptions = { headers: { Accept: 'application/ld+json' } },
} = {}) {
    const inputElement = resolveElement(element)
    const valueInputElement = valueInput ? resolveElement(valueInput) : null

    if (placeholder) {
        inputElement.placeholder = placeholder
    }

    // Disable browser autocomplete to avoid conflicts
    inputElement.setAttribute('autocomplete', 'off')

    let fetchError = null

    const ac = new autoComplete({
        selector: () => inputElement,
        data: {
            src: async (query) => {
                fetchError = null
                const fetchUrl = url.replace('__QUERY__', encodeURIComponent(query))
                try {
                    const res = await fetch(fetchUrl, fetchOptions)
                    if (!res.ok) {
                        throw new Error(`HTTP ${res.status}`)
                    }
                    const data = await res.json()
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
        resultItem: {
            highlight,
        },
        events: {
            input: {
                selection: (event) => {
                    const selection = event.detail.selection.value
                    ac.input.value = selection[labelField]

                    if (valueInputElement) {
                        valueInputElement.value = selection[valueField]
                    }

                    if (onSelection) {
                        onSelection(selection)
                    }
                },
            },
        },
    })

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

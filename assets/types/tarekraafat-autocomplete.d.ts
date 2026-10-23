/**
 * @tarekraafat/autocomplete.js 10 ships no types: the configuration and instance members AutocompleteService uses.
 */
declare module '@tarekraafat/autocomplete.js' {
    interface Feedback<T> {
        query: string
        matches: Array<{ value: T; match: string }>
        results: Array<{ value: T; match: string }>
    }

    interface Selection<T> {
        index: number
        /** Undefined when the item clicked is not a result (a "no result" message) */
        value: T | undefined
    }

    interface Config<T> {
        selector: string | (() => HTMLInputElement)
        /** Wraps the input in a div of its own; false leaves it in place */
        wrapper?: boolean | null
        data: {
            src: T[] | ((query: string) => T[] | Promise<T[]>)
            keys?: string[]
        }
        threshold?: number
        debounce?: number
        resultsList?: {
            noResults?: boolean
            maxResults?: number
            element?: (list: HTMLUListElement, data: Feedback<T>) => void
        }
        /** Returns the record's markup when it matches, undefined otherwise */
        searchEngine?: (query: string, record: unknown) => string | undefined
        events?: {
            input?: {
                selection?: (event: CustomEvent<{ selection: Selection<T> }>) => void
            }
        }
    }

    export default class autoComplete<T = unknown> {
        constructor(config: Config<T>)
        input: HTMLInputElement
        start(query?: string): void
        unInit(): void
    }
}

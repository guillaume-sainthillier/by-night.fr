type Factories<T> = { [K in keyof T]?: () => T[K] }
type Instances<T> = { [K in keyof T]?: { value: T[K] } }

/**
 * Lazy dependency injection container: each entry is built by its factory on
 * first `get()`, then shared.
 */
export default class Container<T extends object> {
    #factories: Factories<T> = {}

    #instances: Instances<T> = {}

    constructor(values: Partial<T> = {}) {
        for (const key in values) {
            const value = values[key]
            if (value !== undefined) {
                this.set(key, () => value)
            }
        }
    }

    set<K extends keyof T>(key: K, factory: () => T[K]): void {
        this.#factories[key] = factory
        delete this.#instances[key]
    }

    get<K extends keyof T>(key: K): T[K] {
        const instance = this.#instances[key]
        if (instance) {
            return instance.value
        }

        const factory = this.#factories[key]
        if (!factory) {
            throw new ReferenceError(`Identifier ${String(key)} is not defined.`)
        }

        const value = factory()
        this.#instances[key] = { value }

        return value
    }

    has(key: keyof T): boolean {
        return this.#factories[key] !== undefined
    }
}

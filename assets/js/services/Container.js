export default class Container {
    #values

    #keys

    #instances

    constructor(values = {}) {
        this.#values = {}
        this.#keys = {}
        this.#instances = {}

        Object.keys(values).forEach((key) => {
            this.set(key, values[key])
        })
    }

    set(key, callback) {
        this.#values[key] = callback
        this.#keys[key] = true
    }

    get(key) {
        if (typeof this.#keys[key] === 'undefined') {
            throw new ReferenceError(`Identifier ${key} is not defined.`)
        }

        if (typeof this.#values[key] !== 'function') {
            return this.#values[key]
        }

        if (typeof this.#instances[key] === 'undefined') {
            this.#instances[key] = this.#values[key]()
        }

        return this.#instances[key]
    }

    has(key) {
        return typeof this.#keys[key] !== 'undefined'
    }
}

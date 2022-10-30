export default class Container {
    constructor(values = {}) {
        this._values = {};
        this._keys = {};
        this._instances = {};

        Object.keys(values).forEach((key) => {
            this.set(key, values[key]);
        });
    }

    set(key, callback) {
        this._values[key] = callback;
        this._keys[key] = true;
    }

    get(key) {
        if (typeof this._keys[key] === 'undefined') {
            throw new ReferenceError(`Identifier ${key} is not defined.`);
        }

        if (typeof this._values[key] !== 'function') {
            return this._values[key];
        }

        if (typeof this._instances[key] === 'undefined') {
            this._instances[key] = this._values[key]();
        }

        return this._instances[key];
    }

    has(key) {
        return typeof this._keys[key] !== 'undefined';
    }
}

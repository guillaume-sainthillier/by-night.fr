NodeList.prototype.forEach = Array.prototype.forEach

export const dom = (selector) => findOne(selector, document)
export const findOne = (selector, element) => (element || document).querySelector(selector)
export const findAll = (selector, element) => (element || document).querySelectorAll(selector)

/**
 * Like `findAll`, but also includes `element` itself when it matches `selector`.
 *
 * `App.mount(container)` scans `container` with each listener's selector, and a
 * descendants-only query would miss the container itself.
 *
 * @returns {Element[]}
 */
export const findAllSelf = (selector, element) => {
    const root = element || document
    const found = [...root.querySelectorAll(selector)]
    if (root.nodeType === Node.ELEMENT_NODE && root.matches(selector)) {
        found.unshift(root)
    }
    return found
}

export const data = (element, name, value) => {
    // Getter
    if (typeof value === 'undefined') {
        return element.dataset[name]
    }

    element.dataset[name] = value
}

export const on = (element, event, handler, useCapture) => element.addEventListener(event, handler, useCapture)
export const trigger = (element, eventName, params) => {
    const event = new CustomEvent(eventName, { detail: params })
    element.dispatchEvent(event)
}

export const remove = (element) => {
    element.parentNode.removeChild(element)
}

export const appendHTML = (element, html) => {
    const child = document.createElement('div')
    child.innerHTML = html

    while (child.firstChild) {
        element.appendChild(child.firstChild)
    }
}

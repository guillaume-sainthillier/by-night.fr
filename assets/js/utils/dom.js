NodeList.prototype.forEach = Array.prototype.forEach

export const dom = (selector) => findOne(selector, document)
export const findOne = (selector, element) => (element || document).querySelector(selector)
export const findAll = (selector, element) => (element || document).querySelectorAll(selector)

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

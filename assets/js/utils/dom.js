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
export const off = (element, event, handler, useCapture) => element.removeEventListener(event, handler, useCapture)
export const trigger = (element, eventName, params) => {
    const event = new CustomEvent(eventName, { detail: params })
    element.dispatchEvent(event)
}

export const sort = (parent, callback) => {
    [...parent.children].sort((a, b) => callback(a, b)).map((node) => parent.appendChild(node))
}

export const submit = (form) => {
    if (data(form, 'action')) {
        form.setAttribute('action', data(form, 'action'))
    }

    data(form, 'submitting', '1')
    trigger(form, 'submit')
}

export const softSubmit = (form) => {
    trigger(form, 'pjax:preSubmit')
}

export const insertAfter = (element, newElement) => {
    if (newElement.parentNode) {
        newElement.parentNode.insertBefore(element, newElement.nextSibling)
    }
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

export const append = (parent, element) => {
    parent.appendChild(element)
}

export const prepend = (element, parent) => {
    parent.insertBefore(element, parent.childNodes[0] || null)
}

export const prependHTML = (element, html) => {
    const child = document.createElement('div')
    child.innerHTML = html

    while (child.firstChild) {
        element.insertBefore(child.firstChild, element.childNodes[0] || null)
    }
}

export const parents = function (elem, selector) {
    const parents = []
    for (; elem && elem !== document; elem = elem.parentNode) {
        if (selector) {
            if (elem.matches(selector)) {
                parents.push(elem)
            }
            continue
        }
        parents.push(elem)
    }

    return parents
}

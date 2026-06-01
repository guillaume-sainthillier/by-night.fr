export const show = (element) => {
    removeClass(element, 'hidden')
}

export const hide = (element) => {
    addClass(element, 'hidden')
}

export const addClass = (element, className) => {
    element.classList.add(className)
}

const hasClass = (element, className) => {
    return element.classList.contains(className)
}

export const removeClass = (element, className) => {
    element.classList.remove(className)
}

export const closest = (element, className) => {
    return element.closest(className)
}

const siblings = (element, className) =>
    Array.prototype.filter.call(
        element.parentNode.children,
        (sibling) => sibling !== element && (!className || hasClass(sibling, className))
    )

export const sibling = (element, className) => siblings(element, className).shift()

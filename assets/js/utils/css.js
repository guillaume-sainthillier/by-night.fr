window.show = (element) => {
    removeClass(element, 'hidden')
}

window.hide = (element) => {
    addClass(element, 'hidden')
}

window.addClass = (element, className) => {
    element.classList.add(className)
}

window.setClass = (element, className) => {
    element.className = className
}

window.hasClass = (element, className) => {
    return element.classList.contains(className)
}

window.toggleClass = (element, className) => {
    element.classList.toggle(className)
}

window.removeClass = (element, className) => {
    element.classList.remove(className)
}

window.closest = (element, className) => {
    return element.closest(className)
}

window.siblings = function (element, className) {
    return Array.prototype.filter.call(element.parentNode.children, function (sibling) {
        return sibling !== element && (!className || hasClass(sibling, className))
    })
}

window.sibling = function (element, className) {
    return siblings(element, className).shift()
}

NodeList.prototype.forEach = Array.prototype.forEach;

window.dom = (selector) => find(selector, document);
window.find = (selector, element) => (element || document).querySelector(selector);
window.findAll = (selector, element) => (element || document).querySelectorAll(selector);

window.data = (element, name, value) => {
    //Getter
    if (typeof value === 'undefined') {
        return element.dataset[name];
    }

    element.dataset[name] = value;
};

window.on = (element, event, handler, useCapture) => element.addEventListener(event, handler, useCapture);
window.off = (element, event, handler, useCapture) => element.removeEventListener(event, handler, useCapture);
window.trigger = (element, eventName, params) => {
    const event = new CustomEvent(eventName, { detail: params });
    element.dispatchEvent(event);
};

window.sort = (parent, callback) => {
    [...parent.children].sort((a, b) => callback(a, b)).map((node) => parent.appendChild(node));
};

window.submit = (form) => {
    if (data(form, 'action')) {
        form.setAttribute('action', data(form, 'action'));
    }

    data(form, 'submitting', '1');
    trigger(form, 'submit');
};

window.softSubmit = (form) => {
    trigger(form, 'pjax:preSubmit');
};

window.insertAfter = (element, newElement) => {
    if (newElement.parentNode) {
        newElement.parentNode.insertBefore(element, newElement.nextSibling);
    }
};

window.remove = (element) => {
    element.parentNode.removeChild(element);
};

window.appendHTML = (element, html) => {
    const child = document.createElement('div');
    child.innerHTML = html;

    while (child.firstChild) {
        element.appendChild(child.firstChild);
    }
};

window.append = (parent, element) => {
    parent.appendChild(element);
};

window.prepend = (element, parent) => {
    parent.insertBefore(element, parent.childNodes[0] || null);
};

window.prependHTML = (element, html) => {
    const child = document.createElement('div');
    child.innerHTML = html;

    while (child.firstChild) {
        element.insertBefore(child.firstChild, element.childNodes[0] || null);
    }
};

window.parents = function (elem, selector) {
    let parents = [];
    for (; elem && elem !== document; elem = elem.parentNode) {
        if (selector) {
            if (elem.matches(selector)) {
                parents.push(elem);
            }
            continue;
        }
        parents.push(elem);
    }

    return parents;
};

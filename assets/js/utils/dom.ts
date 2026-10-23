export const dom = <E extends HTMLElement = HTMLElement>(selector: string): E | null => findOne<E>(selector, document)
export const findOne = <E extends HTMLElement = HTMLElement>(selector: string, element?: ParentNode | null): E | null =>
    (element || document).querySelector<E>(selector)
export const findAll = <E extends HTMLElement = HTMLElement>(
    selector: string,
    element?: ParentNode | null
): NodeListOf<E> => (element || document).querySelectorAll<E>(selector)

export const data = (element: HTMLElement, name: string): string | undefined => element.dataset[name]

export const on = <K extends keyof HTMLElementEventMap>(
    element: HTMLElement,
    event: K,
    handler: (event: HTMLElementEventMap[K]) => void,
    useCapture?: boolean
) => element.addEventListener(event, handler, useCapture)

export const trigger = <T>(element: Element, eventName: string, params?: T) => {
    const event = new CustomEvent<T | undefined>(eventName, { detail: params })
    element.dispatchEvent(event)
}

export const remove = (element: Element) => {
    element.remove()
}

export const appendHTML = (element: Element, html: string) => {
    const child = document.createElement('div')
    child.innerHTML = html

    while (child.firstChild) {
        element.appendChild(child.firstChild)
    }
}

export const closest = (element: Element, selector: string): HTMLElement | null => {
    return element.closest<HTMLElement>(selector)
}

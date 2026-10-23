import { findAll } from '@/js/utils/dom'

declare global {
    interface Window {
        /** The element a social login popup was opened from, read back by templates/security/connect-success.html.twig */
        parent_elem?: Element
    }
}

export const isTouchDevice = () => {
    return 'ontouchstart' in window || navigator.maxTouchPoints > 0
}

export const popup = (href: string, parent: Element) => {
    window.parent_elem = parent

    const width = 520
    const height = 350
    const leftPosition = window.screen.width / 2 - (width / 2 + 10)
    const topPosition = window.screen.height / 2 - (height / 2 + 50)
    const windowFeatures = `status=no,height=${height},width=${width},left=${leftPosition},top=${topPosition},screenX=${leftPosition},screenY=${topPosition},toolbar=0,status=0`

    window.open(href, 'sharer', windowFeatures)
}

/**
 * The ids of a form's fields, by name: `{name: id}`, and for a collection `{_name: collectionId, name: [items]}`.
 */
export interface VirtualForm {
    [name: string]: string | VirtualForm[]
}

const lastNamePart = (name: string, separator: RegExp): string =>
    name
        .split(separator)
        .filter((el) => el !== '')
        .slice(-1)
        .pop() ?? ''

export const getVirtualForm = (container: ParentNode): VirtualForm => {
    const virtualForm: VirtualForm = {}
    findAll('.form-control, .custom-file-input, .custom-control-input, input[type="hidden"]', container).forEach(
        (field) => {
            const name = field.getAttribute('name')
            if (name !== null && field.id) {
                virtualForm[lastNamePart(name, /\]|\[/g)] = field.id
            }
        }
    )

    findAll('.form-collection', container).forEach((field) => {
        if (field.id) {
            const name = lastNamePart(field.id, /_/g)
            const items: VirtualForm[] = []
            findAll('.collection-group', field).forEach((collectionItem) => {
                items.push(getVirtualForm(collectionItem))
            })
            virtualForm[`_${name}`] = field.id
            virtualForm[name] = items
        }
    })

    return virtualForm
}

export const setElementValue = (element: HTMLElement, value: unknown) => {
    if (element instanceof HTMLInputElement && element.type === 'checkbox') {
        element.checked = !!value
    } else if (
        element instanceof HTMLInputElement ||
        element instanceof HTMLSelectElement ||
        element instanceof HTMLTextAreaElement
    ) {
        element.value = String(value ?? '')
    }
}

import { getElementValue, setElementValue } from '@/js/utils/utils'
import {closest, sibling} from "@/js/utils/css"
import {dom, findOne} from "@/js/utils/dom"

export default class ElementManager {
    constructor() {
        this.lastElement = null
    }

    getContainerElement(field) {
        if (typeof field === 'string') {
            field = { id: field }
        } else if (field instanceof Node) {
            field = { element: field }
        }

        field = {
            id: '',
            element: null,
            type: 'formGroup',
            ...field,
        }

        const element = this.getElement(field)

        if (field.type === 'formGroup') {
            const container = closest(element, '.form-group')
            if (!container) {
                throw new Error(`Unable to find form-group parent for element with ID "${field.id}"`)
            }

            return container
        }
        if (field.type === 'block') {
            return element
        }

        throw new Error(`Unable to find element with ID "${field.id}" and type "${field.type}"!`)
    }

    getElement(field) {
        if (typeof field === 'string') {
            field = { id: field }
        } else if (field instanceof Node) {
            field = { element: field }
        }

        field = {
            id: '',
            element: null,
            type: 'element',
            ...field,
        }

        if (field.element !== null) {
            this.lastElement = field.element
            return field.element
        }

        if (field.id === null || undefined === field.id || field.id === '') {
            throw new Error(
                `No given id for a field!${this.lastElement ? ` Last element ID : ${this.lastElement.id}` : ''}`
            )
        }

        const element = dom(`#${field.id}`)
        if (!element) {
            throw new Error(`Unable to find element with ID "${field.id}"!`)
        }

        this.lastElement = element
        return element
    }

    getElementLabel(element) {
        if (element.type === 'checkbox') {
            return sibling(element, 'custom-control-label')
        }

        const container = closest(element, '.form-group')
        if (container) {
            return findOne('label,legend', container)
        }

        return null
    }

    getElementValue(element) {
        return getElementValue(element)
    }

    setElementValue(element, value) {
        setElementValue(element, value)
    }
}

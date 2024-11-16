import ElementManager from './ElementManager'
import { constructArrayDefinition } from '@/js/utils/utils'
import {hide, show} from "@/js/utils/css"
import {on} from "@/js/utils/dom"

export default class VisibleManager {
    constructor() {
        this.elementManager = new ElementManager()
    }

    handle(toggleDefinition, reverseDefinition) {
        for (const [elementId, definition] of Object.entries(toggleDefinition)) {
            const element = this.elementManager.getElement(elementId)

            const fnUpdateElement = () => {
                const elementValue = this.elementManager.getElementValue(element)
                this.toggle(elementValue, definition, reverseDefinition)
            }

            fnUpdateElement()
            on(element, 'change', fnUpdateElement)
        }
    }

    toggle(elementValue, toggleDefinition, reverseDefinition) {
        toggleDefinition = constructArrayDefinition(toggleDefinition)

        // Hide fields first
        for (const [toggleValue, fields] of Object.entries(toggleDefinition)) {
            if (toggleValue !== elementValue) {
                this._toggleFields(fields, reverseDefinition)
            }
        }

        // Then show
        if (toggleDefinition[elementValue]) {
            this._toggleFields(toggleDefinition[elementValue], !reverseDefinition)
        }

        this.lastElement = null
    }

    _toggleFields(fields, show) {
        fields.forEach((field) => {
            if (show) {
                this._show(field)
            } else {
                this._hide(field)
            }
        })
    }

    _show(field) {
        show(this.elementManager.getContainerElement(field))
    }

    _hide(field) {
        hide(this.elementManager.getContainerElement(field))
    }
}

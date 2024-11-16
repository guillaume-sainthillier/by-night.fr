import ElementManager from './ElementManager'
import { constructArrayDefinition } from '@/js/utils/utils'
import {on} from "@/js/utils/dom"
import {addClass, removeClass} from "@/js/utils/css"

export default class RequiredManager {
    constructor() {
        this.elementManager = new ElementManager()
    }

    handle(requireDefinition, reverseDefinition) {
        for (const [elementId, definition] of Object.entries(requireDefinition)) {
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

        // Disable fields first
        for (const [toggleValue, fields] of Object.entries(toggleDefinition)) {
            if (elementValue !== toggleValue) {
                this._toggleFields(fields, reverseDefinition)
            }
        }

        // Then enable
        if (toggleDefinition[elementValue]) {
            this._toggleFields(toggleDefinition[elementValue], !reverseDefinition)
        }
    }

    _toggleFields(fields, required) {
        fields.forEach((field) => {
            this.setRequired(field, required)
        })
    }

    setRequired(field, required) {
        const element = this.elementManager.getElement(field)
        const label = this.elementManager.getElementLabel(element)

        if (required === true) {
            element.setAttribute('required', 'required')
            if (label) addClass(label, 'required')
        } else {
            element.removeAttribute('required')
            if (label) removeClass(label, 'required')
        }
    }
}

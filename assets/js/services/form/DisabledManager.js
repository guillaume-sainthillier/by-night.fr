import ElementManager from './ElementManager';
import { constructArrayDefinition } from '../../utils/utils';

export default class DisabledManager {
    constructor() {
        this.elementManager = new ElementManager();
    }

    handle(disableDefinition, reverseDefinition) {
        for (const [elementId, definition] of Object.entries(disableDefinition)) {
            const element = this.elementManager.getElement(elementId);

            const fnUpdateElement = () => {
                const elementValue = this.elementManager.getElementValue(element);
                this.toggle(elementValue, definition, reverseDefinition);
            };

            fnUpdateElement();
            on(element, 'change', fnUpdateElement);
        }
    }

    toggle(elementValue, toggleDefinition, reverseDefinition) {
        toggleDefinition = constructArrayDefinition(toggleDefinition);

        // Disable fields first
        for (const [toggleValue, fields] of Object.entries(toggleDefinition)) {
            if (elementValue !== toggleValue) {
                this._toggleFields(fields, reverseDefinition);
            }
        }

        // Then enable
        if (toggleDefinition[elementValue]) {
            this._toggleFields(toggleDefinition[elementValue], !reverseDefinition);
        }
    }

    setDisabled(field, value) {
        this._setState(field, 'disabled', value);
    }

    _toggleFields(fields, value) {
        fields.forEach((field) => {
            this._setState(field, 'disabled', value);
        });
    }

    _setState(field, name, value) {
        const element = this.elementManager.getElement(field);
        if (value === true) {
            element.setAttribute(name, name);
        } else {
            element.removeAttribute(name);
        }
    }
}

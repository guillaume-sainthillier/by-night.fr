import ElementManager from './ElementManager';
import { constructArrayDefinition } from '../../utils/utils';

export default class DisabledManager {
    constructor() {
        this.elementManager = new ElementManager();
    }

    handle(disableDefinition, reverseDefinition) {
        for (let elementId in disableDefinition) {
            if (disableDefinition.hasOwnProperty(elementId)) {
                const element = this.elementManager.getElement(elementId);

                const fnUpdateElement = () => {
                    const elementValue = this.elementManager.getElementValue(element);
                    this.toggle(elementValue, disableDefinition[elementId], reverseDefinition);
                };

                fnUpdateElement();
                on(element, 'change', fnUpdateElement);
            }
        }
    }

    toggle(elementValue, toggleDefinition, reverseDefinition) {
        toggleDefinition = constructArrayDefinition(toggleDefinition);

        // Disable fields first
        for (let toggleValue in toggleDefinition) {
            if (toggleDefinition.hasOwnProperty(toggleValue) && elementValue !== toggleValue) {
                this._toggleFields(toggleDefinition[toggleValue], reverseDefinition);
            }
        }

        //Then enable
        if (toggleDefinition[elementValue]) {
            this._toggleFields(toggleDefinition[elementValue], !reverseDefinition);
        }
    }

    _toggleFields(fields, value) {
        fields.forEach((field) => {
            this._setState(field, 'disabled', value);
        });
    }

    _setState(field, name, value) {
        const element = this.elementManager.getElement(field);
        if (true === value) {
            element.setAttribute(name, name);
        } else {
            element.removeAttribute(name);
        }
    }
}

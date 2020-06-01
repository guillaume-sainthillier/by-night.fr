import ElementManager from './ElementManager';
import { constructArrayDefinition } from '../../utils/utils';

export default class RequiredManager {
    constructor() {
        this.elementManager = new ElementManager();
    }

    handle(requireDefinition, reverseDefinition) {
        for (let elementId in requireDefinition) {
            if (requireDefinition.hasOwnProperty(elementId)) {
                const element = this.elementManager.getElement(elementId);

                const fnUpdateElement = () => {
                    const elementValue = this.elementManager.getElementValue(element);
                    this.toggle(elementValue, requireDefinition[elementId], reverseDefinition);
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

    _toggleFields(fields, required) {
        fields.forEach((field) => {
            this._setRequired(field, required);
        });
    }

    _setRequired(field, required) {
        const element = this.elementManager.getElement(field);
        const label = this.elementManager.getElementLabel(element);

        if (true === required) {
            element.setAttribute('required', 'required');
            label && addClass(label, 'required');
        } else {
            element.removeAttribute('required');
            label && removeClass(label, 'required');
        }
    }
}

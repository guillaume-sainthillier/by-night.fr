import ElementManager from './ElementManager';
import { constructArrayDefinition } from '../../utils/utils';

export default class VisibleManager {
    constructor() {
        this.elementManager = new ElementManager();
    }

    handle(toggleDefinition, reverseDefinition) {
        for (let elementId in toggleDefinition) {
            if (toggleDefinition.hasOwnProperty(elementId)) {
                const element = this.elementManager.getElement(elementId);
                const fnUpdateElement = () => {
                    const elementValue = this.elementManager.getElementValue(element);
                    this.toggle(elementValue, toggleDefinition[elementId], reverseDefinition);
                };

                fnUpdateElement();
                on(element, 'change', fnUpdateElement);
            }
        }
    }

    toggle(elementValue, toggleDefinition, reverseDefinition) {
        toggleDefinition = constructArrayDefinition(toggleDefinition);

        // Hide fields first
        for (let toggleValue in toggleDefinition) {
            if (toggleDefinition.hasOwnProperty(toggleValue) && toggleValue !== elementValue) {
                this._toggleFields(toggleDefinition[toggleValue], reverseDefinition);
            }
        }

        //Then show
        if (toggleDefinition[elementValue]) {
            this._toggleFields(toggleDefinition[elementValue], !reverseDefinition);
        }

        this.lastElement = null;
    }

    _toggleFields(fields, show) {
        fields.forEach((field) => {
            if (show) {
                this._show(field);
            } else {
                this._hide(field);
            }
        });
    }

    _show(field) {
        show(this.elementManager.getContainerElement(field));
    }

    _hide(field) {
        hide(this.elementManager.getContainerElement(field));
    }
}

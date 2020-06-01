import { getElementValue, setElementValue } from '../../utils/utils';

export default class ElementManager {
    constructor() {
        this.lastElement = null;
    }

    getContainerElement(field) {
        if (typeof field === 'string') {
            field = { id: field };
        } else if (field instanceof Node) {
            field = { element: field };
        }

        field = Object.assign(
            {
                id: '',
                element: null,
                type: 'formGroup',
            },
            field
        );

        const element = this.getElement(field);

        if (field.type === 'formGroup') {
            const container = closest(element, '.form-group');
            if (!container) {
                throw `Unable to find form-group parent for element with ID "${field.id}"`;
            }

            return container;
        } else if (field.type === 'block') {
            return element;
        }

        throw `Unable to find element with ID "${field.id}" and type "${field.type}"!`;
    }

    getElement(field) {
        if (typeof field === 'string') {
            field = { id: field };
        } else if (field instanceof Node) {
            field = { element: field };
        }

        field = Object.assign(
            {
                id: '',
                element: null,
                type: 'element',
            },
            field
        );

        if (null !== field.element) {
            this.lastElement = field.element;
            return field.element;
        }

        if (null === field.id || undefined === field.id || '' === field.id) {
            throw 'No given id for a field!' + (this.lastElement ? ` Last element ID : ${this.lastElement.id}` : '');
        }

        const element = dom(`#${field.id}`);
        if (!element) {
            throw `Unable to find element with ID "${field.id}"!`;
        }

        this.lastElement = element;
        return element;
    }

    getElementLabel(element) {
        if (element.type === 'checkbox') {
            return sibling(element, 'custom-control-label');
        }

        const container = closest(element, '.form-group');
        if (container) {
            return find('label', container);
        }

        return null;
    }

    getElementValue(element) {
        return getElementValue(element);
    }

    setElementValue(element, value) {
        setElementValue(element, value);
    }
}

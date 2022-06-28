import ElementManager from '../services/form/ElementManager';

export const isTouchDevice = () => {
    return 'ontouchstart' in window || navigator.maxTouchPoints > 0 || navigator.msMaxTouchPoints > 0;
};

export const popup = (href, parent) => {
    window.parent_elem = parent;

    var width = 520,
        height = 350,
        leftPosition = window.screen.width / 2 - (width / 2 + 10),
        topPosition = window.screen.height / 2 - (height / 2 + 50),
        windowFeatures =
            'status=no,height=' +
            height +
            ',width=' +
            width +
            ',left=' +
            leftPosition +
            ',top=' +
            topPosition +
            ',screenX=' +
            leftPosition +
            ',screenY=' +
            topPosition +
            ',toolbar=0,status=0';

    window.open(href, 'sharer', windowFeatures);
};

export const removeSelectOptions = (select, removeEmptyOption = false) => {
    [...findAll('option', select)]
        .filter((option) => removeEmptyOption === false && !!option.value)
        .forEach((option) => select.removeChild(option));
};

export const updateQueryStringParameter = (uri, key, value) => {
    var re = new RegExp('([?&])' + key + '=.*?(&|$)', 'i');
    var separator = uri.indexOf('?') !== -1 ? '&' : '?';

    if (uri.match(re)) {
        return uri.replace(re, '$1' + key + '=' + value + '$2');
    } else {
        return uri + separator + key + '=' + value;
    }
};

export const constructArrayDefinition = (definitions) => {
    let theDefinitions = {};

    for (let definitionName in definitions) {
        if (definitions.hasOwnProperty(definitionName)) {
            definitionName.split(',').forEach((splitedDefinitionName) => {
                theDefinitions[splitedDefinitionName] = (theDefinitions[splitedDefinitionName] || []).concat(
                    definitions[definitionName]
                );
            });
        }
    }

    return theDefinitions;
};

export const constructObjectDefinition = (definitions) => {
    let theDefinitions = {};

    for (let definitionName in definitions) {
        if (definitions.hasOwnProperty(definitionName)) {
            definitionName.split(',').forEach((splitedDefinitionName) => {
                theDefinitions[splitedDefinitionName] = Object.assign(
                    theDefinitions[splitedDefinitionName] || {},
                    definitions[definitionName]
                );
            });
        }
    }

    return theDefinitions;
};

export const getVirtualForm = (container) => {
    let virtualForm = {};
    findAll('.form-control, .custom-file-input, .custom-control-input', container).forEach((field) => {
        if (field.hasAttribute('name') && field.id) {
            let name = field
                .getAttribute('name')
                .split(/\]|\[/g)
                .filter((el) => el !== '')
                .slice(-1)
                .pop();
            virtualForm[name] = field.id;
        }
    });

    findAll('.form-collection', container).forEach((field) => {
        if (field.id) {
            const name = field.id
                .split(/_/g)
                .filter((el) => el !== '')
                .slice(-1)
                .pop();
            virtualForm[`_${name}`] = field.id;
            virtualForm[name] = [];
            findAll('.collection-group', field).forEach((collectionItem) => {
                virtualForm[name].push(getVirtualForm(collectionItem));
            });
        }
    });

    return virtualForm;
};

export const getFormValues = (form) => {
    const elementManager = new ElementManager();
    let formValues = {};
    for (let elementName in form) {
        if (!form.hasOwnProperty(elementName) || elementName.startsWith('_')) {
            continue;
        }

        const elementId = form[elementName];
        if (Array.isArray(elementId)) {
            formValues[elementName] = [];
            elementId.forEach((collectionElement) => {
                formValues[elementName].push(getFormValues(collectionElement));
            });
        } else if (typeof elementId === 'object') {
            formValues[elementName] = getFormValues(elementId);
        } else {
            const element = dom(`#${elementId}`);
            formValues[elementName] = elementManager.getElementValue(element);
        }
    }

    return formValues;
};

export const getElementValue = (element) => {
    if (element.type === 'checkbox') {
        return element.checked;
    } else if (element.tagName === 'SELECT') {
        if (element.selectedIndex === -1) {
            return null;
        }

        const value = data(element.options[element.selectedIndex], 'value');
        if (value !== undefined) {
            return value;
        }
    }

    return element.value;
};

export const setElementValue = (element, value) => {
    if (element.type === 'checkbox') {
        element.checked = !!value;
    } else {
        element.value = value;
    }
};

export const uuid = () => {
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
        var r = (Math.random() * 16) | 0,
            v = c === 'x' ? r : (r & 0x3) | 0x8;
        return v.toString(16);
    });
};

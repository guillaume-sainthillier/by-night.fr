import { getVirtualForm, setElementValue } from '../../utils/utils';

export default class CollectionManager {
    /**
     * @param {ModalManager} modalManager
     */
    constructor(modalManager) {
        this.modalManager = modalManager;
    }

    handle(collection, _collection, itemHandler) {
        // Existing items
        for (let index in typeof collection === 'object' ? collection : []) {
            if (collection.hasOwnProperty(index) && !index.startsWith('_')) {
                const form = collection[index];
                itemHandler(form);
            }
        }

        //New item
        const collectionElement = dom(`#${_collection || collection}`);
        on(collectionElement, 'collection.add', (e) => {
            const item = e.detail.item;
            let virtualForm = getVirtualForm(item);
            itemHandler(virtualForm);
        });
    }

    addElement(collection, elementValues = {}, countCallBack = null) {
        const counter = countCallBack ? countCallBack(collection) : collection.children.length;

        var prototype = data(collection, 'prototype');
        var prototypeName = data(collection, 'prototypeName') || '__name__';
        prototype = prototype.replace(new RegExp(prototypeName, 'g'), counter);

        appendHTML(collection, prototype);
        const newCollectionElement = collection.lastElementChild;
        if (elementValues) {
            this._populate(newCollectionElement, elementValues);
        }
        trigger(collection, 'collection.add', { item: newCollectionElement });
        App.dispatchPageLoadedEvent(newCollectionElement);
        trigger(collection, 'collection.added', { item: newCollectionElement });

        const btnRemove = find('.remove-collection', newCollectionElement);
        if (btnRemove) {
            on(btnRemove, 'click', (e) => {
                e.preventDefault();
                this.removeElement(btnRemove);
            });
        }
    }

    _populate(elementDOM, elementValues) {
        const virtualForm = getVirtualForm(elementDOM);
        for (let formElementName in virtualForm) {
            //Skip _element (= collection or nested form element)
            if (!virtualForm.hasOwnProperty(formElementName) || formElementName.startsWith('_')) {
                continue;
            }

            // Value is missing
            if (typeof elementValues[formElementName] === 'undefined') {
                continue;
            }

            const value = elementValues[formElementName];

            //Collection elements
            if (Array.isArray(elementValues[formElementName])) {
                if (typeof virtualForm[`_${formElementName}`] !== 'undefined') {
                    const parent = dom(`#${virtualForm[`_${formElementName}`]}`);
                    elementValues[formElementName].forEach((childElementValues) => {
                        this.addElement(parent, childElementValues);
                    });
                } else {
                    console.warn(`Element _${formElementName} not found in `, virtualForm);
                }
                continue;
            }

            // Nested element
            if (typeof virtualForm[formElementName] === 'object') {
                if (typeof virtualForm[`_${formElementName}`] !== 'undefined') {
                    const element = dom(`#${virtualForm[`_${formElementName}`]}`);
                    this._populate(element, value);
                } else {
                    console.warn(`Element _${formElementName} not found in `, virtualForm);
                }
                continue;
            }

            const element = dom(`#${virtualForm[formElementName]}`);
            setElementValue(element, value && typeof value.id !== 'undefined' ? value.id : value);
        }
    }

    removeElement(btn, withConfirmation = true) {
        if (false === withConfirmation || !data(btn, 'confirmMessage')) {
            this._doRemove(btn);
            return;
        }

        this.modalManager
            .createConfirm({
                text: data(btn, 'confirmMessage'),
                focusConfirm: true,
                focusCancel: false,
            })
            .then((result) => {
                if (result) {
                    this._doRemove(btn);
                }
            });
    }

    emptyCollection(collection) {
        findAll('.remove-collection', collection).forEach((btn) => {
            this._doRemove(btn);
        });
    }

    _doRemove(btn) {
        const collection = closest(btn, '.form-collection');
        const group = closest(btn, data(btn, 'group') || '.form-group');

        remove(group);
        trigger(collection, 'collection.deleted');
    }
}

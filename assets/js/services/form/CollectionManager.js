import { getVirtualForm, setElementValue } from '@/js/utils/utils'
import {appendHTML, data, dom, findAll, on, trigger} from "@/js/utils/dom"
import {closest} from "@/js/utils/css"

export default class CollectionManager {
    /**
     * @param {ModalManager} modalManager
     */
    constructor(modalManager) {
        this.modalManager = modalManager
    }

    handle(collection, _collection, itemHandler) {
        // Existing items
        for (const [index, form] of typeof collection === 'object' ? Object.entries(collection) : []) {
            if (!index.startsWith('_')) {
                itemHandler(form)
            }
        }

        // New item
        const collectionDOM = dom(`#${_collection || collection}`)
        on(collectionDOM, 'collection.add', (e) => {
            const collectionItem = e.detail.item
            itemHandler(getVirtualForm(collectionItem))
        })
    }

    addElement(collection, elementValues = {}, countCallBack = null, dispatchEvent = true) {
        const counter = countCallBack ? countCallBack(collection) : collection.children.length

        let prototypeItemHTML = data(collection, 'prototype')
        const prototypeItemName = data(collection, 'prototypeName') || '__name__'
        prototypeItemHTML = prototypeItemHTML.replace(new RegExp(prototypeItemName, 'g'), counter)

        appendHTML(collection, prototypeItemHTML)
        const collectionItem = collection.lastElementChild
        if (elementValues) {
            this._populate(collectionItem, elementValues)
        }

        if (dispatchEvent === true) {
            trigger(collection, 'collection.add', { item: collectionItem })
            window.App.dispatchPageLoadedEvent(collectionItem)
            trigger(collection, 'collection.added', { item: collectionItem })
        }
    }

    _populate(elementDOM, elementValues) {
        const virtualForm = getVirtualForm(elementDOM)
        for (const [formElementName, formElement] of Object.entries(virtualForm)) {
            // Skip _element (= collection or nested form element)
            if (formElementName.startsWith('_')) {
                continue
            }

            // Value is missing
            if (typeof elementValues[formElementName] === 'undefined') {
                continue
            }

            const value = elementValues[formElementName]

            // Collection elements
            if (Array.isArray(elementValues[formElementName])) {
                if (typeof virtualForm[`_${formElementName}`] !== 'undefined') {
                    const parent = dom(`#${virtualForm[`_${formElementName}`]}`)
                    elementValues[formElementName].forEach((childElementValues) => {
                        // Don't dispatch events for nested collection items as main item will be dispatched
                        this.addElement(parent, childElementValues, null, false)
                    })
                } else if (Array.isArray(value)) {
                    // Select multiple
                    const element = dom(`#${formElement}`)
                    setElementValue(element, value)
                } else {
                    console.warn(`Element _${formElementName} not found in `, virtualForm)
                }
                continue
            }

            // Nested element
            if (typeof formElement === 'object') {
                if (typeof virtualForm[`_${formElementName}`] !== 'undefined') {
                    const element = dom(`#${virtualForm[`_${formElementName}`]}`)
                    this._populate(element, value)
                } else {
                    // Embedded
                    for (const [formChildElementName, formChildElement] of Object.entries(formElement)) {
                        const element = dom(`#${formChildElement}`)
                        this._populate(element.parentNode, { [formChildElementName]: value[formChildElementName] })
                    }
                }
                continue
            }

            const element = dom(`#${formElement}`)
            setElementValue(element, value && typeof value.id !== 'undefined' ? value.id : value)
        }
    }

    removeElement(btn, withConfirmation = true) {
        if (withConfirmation === false) {
            this._doRemove(btn)
            return
        }

        this.modalManager
            .createConfirm({
                text: data(btn, 'confirmMessage'),
                focusConfirm: true,
                focusCancel: false,
            })
            .then((result) => {
                if (result) {
                    this._doRemove(btn)
                }
            })
    }

    emptyCollection(collection) {
        findAll('.remove-collection', collection).forEach((btn) => {
            this._doRemove(btn)
        })
    }

    _doRemove(btn) {
        const collection = closest(btn, '.collection')
        const collectionItem = closest(btn, data(btn, 'item') || '.form-group')

        // eslint-disable-next-line no-undef
        remove(collectionItem)
        trigger(collection, 'collection.deleted')
    }
}

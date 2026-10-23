import { appendHTML, findAll, findOne, trigger } from '@silarhi/app-js'
import type ModalManager from '@/js/services/modals/ModalManager'
import { getVirtualForm, setElementValue } from '@/js/utils/utils'

interface CollectionItemDetail {
    item: HTMLElement
}

declare global {
    interface HTMLElementEventMap {
        'collection.add': CustomEvent<CollectionItemDetail>
        'collection.added': CustomEvent<CollectionItemDetail>
        'collection.deleted': CustomEvent<undefined>
    }
}

type FieldValue = string | number | boolean | null | { id: string | number }

/**
 * The values of a new collection item, by field name; an array fills a nested collection (or a select multiple).
 */
export interface CollectionItemValues {
    [name: string]: FieldValue | string[] | CollectionItemValues[] | undefined
}

export default class CollectionManager {
    modalManager: ModalManager

    constructor(modalManager: ModalManager) {
        this.modalManager = modalManager
    }

    addElement(
        collection: HTMLElement,
        elementValues: CollectionItemValues = {},
        countCallBack: ((collection: HTMLElement) => number) | null = null,
        dispatchEvent = true
    ) {
        const counter = countCallBack ? countCallBack(collection) : collection.children.length

        const prototypeItemName = collection.dataset.prototypeName || '__name__'
        const prototypeItemHTML = (collection.dataset.prototype ?? '').replace(
            new RegExp(prototypeItemName, 'g'),
            String(counter)
        )

        appendHTML(collection, prototypeItemHTML)
        const collectionItem = collection.lastElementChild
        if (!(collectionItem instanceof HTMLElement)) {
            return
        }

        this._populate(collectionItem, elementValues)

        if (dispatchEvent === true) {
            trigger<CollectionItemDetail>(collection, 'collection.add', { item: collectionItem })
            window.App.mount(collectionItem)
            trigger<CollectionItemDetail>(collection, 'collection.added', { item: collectionItem })
        }
    }

    _populate(elementDOM: HTMLElement, elementValues: CollectionItemValues) {
        const virtualForm = getVirtualForm(elementDOM)
        for (const [formElementName, formElement] of Object.entries(virtualForm)) {
            // Skip _element (= collection or nested form element)
            if (formElementName.startsWith('_')) {
                continue
            }

            const value = elementValues[formElementName]

            // Value is missing
            if (typeof value === 'undefined') {
                continue
            }

            // Collection elements
            if (Array.isArray(value)) {
                const collectionId = virtualForm[`_${formElementName}`]
                const parent = typeof collectionId === 'string' ? findOne(`#${collectionId}`) : null
                if (parent) {
                    value.forEach((childElementValues) => {
                        if (typeof childElementValues === 'object') {
                            // Don't dispatch events for nested collection items as main item will be dispatched
                            this.addElement(parent, childElementValues, null, false)
                        }
                    })
                } else if (typeof formElement === 'string') {
                    // Select multiple
                    const element = findOne(`#${formElement}`)
                    if (element) {
                        setElementValue(element, value)
                    }
                }
                continue
            }

            if (typeof formElement !== 'string') {
                console.warn(`Element ${formElementName} is a collection in `, virtualForm)
                continue
            }

            const element = findOne(`#${formElement}`)
            if (element) {
                setElementValue(element, value !== null && typeof value === 'object' ? value.id : value)
            }
        }
    }

    removeElement(btn: HTMLElement, withConfirmation = true) {
        if (withConfirmation === false) {
            this._doRemove(btn)
            return
        }

        this.modalManager
            .createConfirm({
                text: btn.dataset.confirmMessage,
                focusConfirm: true,
                focusCancel: false,
            })
            .then((result) => {
                if (result) {
                    this._doRemove(btn)
                }
            })
    }

    emptyCollection(collection: HTMLElement) {
        findAll('.remove-collection', collection).forEach((btn) => {
            this._doRemove(btn)
        })
    }

    _doRemove(btn: HTMLElement) {
        const collection = btn.closest('.collection')
        const collectionItem = btn.closest(btn.dataset.item || '.form-group')
        if (!collectionItem) {
            return
        }

        window.App.unmount(collectionItem)
        collectionItem.remove()
        if (collection) {
            trigger(collection, 'collection.deleted')
        }
    }
}

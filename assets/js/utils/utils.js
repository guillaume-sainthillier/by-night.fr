import { data, dom, findAll } from '@/js/utils/dom'

export const isTouchDevice = () => {
    return 'ontouchstart' in window || navigator.maxTouchPoints > 0 || navigator.msMaxTouchPoints > 0
}

export const popup = (href, parent) => {
    window.parent_elem = parent

    const width = 520
    const height = 350
    const leftPosition = window.screen.width / 2 - (width / 2 + 10)
    const topPosition = window.screen.height / 2 - (height / 2 + 50)
    const windowFeatures = `status=no,height=${height},width=${width},left=${leftPosition},top=${topPosition},screenX=${leftPosition},screenY=${topPosition},toolbar=0,status=0`

    window.open(href, 'sharer', windowFeatures)
}

export const constructArrayDefinition = (definitions) => {
    const theDefinitions = {}

    for (const definitionName of definitions) {
        definitionName.split(',').forEach((splitedDefinitionName) => {
            theDefinitions[splitedDefinitionName] = (theDefinitions[splitedDefinitionName] || []).concat(
                definitions[definitionName]
            )
        })
    }

    return theDefinitions
}

export const constructObjectDefinition = (definitions) => {
    const theDefinitions = {}

    for (const [definitionName, definitionValue] of Object.entries(definitions)) {
        definitionName.split(',').forEach((splitedDefinitionName) => {
            const splitedTrimmedDefinitionName = splitedDefinitionName.trim()
            theDefinitions[splitedTrimmedDefinitionName] = Object.assign(
                theDefinitions[splitedTrimmedDefinitionName] || {},
                definitionValue
            )
        })
    }

    return theDefinitions
}

export const getVirtualForm = (container) => {
    const virtualForm = {}
    findAll('.form-control, .custom-file-input, .custom-control-input, input[type="hidden"]', container).forEach(
        (field) => {
            if (field.hasAttribute('name') && field.id) {
                const name = field
                    .getAttribute('name')
                    .split(/\]|\[/g)
                    .filter((el) => el !== '')
                    .slice(-1)
                    .pop()
                virtualForm[name] = field.id
            }
        }
    )

    findAll('.form-collection', container).forEach((field) => {
        if (field.id) {
            const name = field.id
                .split(/_/g)
                .filter((el) => el !== '')
                .slice(-1)
                .pop()
            virtualForm[`_${name}`] = field.id
            virtualForm[name] = []
            findAll('.collection-group', field).forEach((collectionItem) => {
                virtualForm[name].push(getVirtualForm(collectionItem))
            })
        }
    })

    return virtualForm
}

const getFormValues = (form) => {
    const formValues = {}
    for (const [elementName, elementId] of Object.entries(form)) {
        if (elementName.startsWith('_')) {
            continue
        }

        if (Array.isArray(elementId)) {
            formValues[elementName] = []
            elementId.forEach((collectionItem, i) => {
                formValues[elementName][i] = getFormValues(collectionItem)
            })
        } else if (typeof elementId === 'object') {
            formValues[elementName] = getFormValues(elementId)
        } else {
            const element = dom(`#${elementId}`)
            formValues[elementName] = getElementValue(element)
        }
    }

    return formValues
}

export const getElementValue = (element) => {
    if (element.type === 'checkbox') {
        return element.checked
    }
    if (element.tagName === 'SELECT') {
        if (element.selectedIndex === -1) {
            return null
        }

        const value = data(element.options[element.selectedIndex], 'value')
        if (value !== undefined) {
            return value
        }
    }

    return element.value
}

export const setElementValue = (element, value) => {
    if (element.type === 'checkbox') {
        element.checked = !!value
    } else {
        element.value = value
    }
}

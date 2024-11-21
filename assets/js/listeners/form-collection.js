import {findAll, findOne, on} from "@/js/utils/dom"
import {closest} from "@/js/utils/css"

export default (di, container) => {
    const collectionManager = di.get('collectionManager')

    findAll('.add-collection', container).forEach((btn) => {
        on(btn, 'click', (e) => {
            e.preventDefault()

            const wrapper = closest(btn, '.collection-wrapper')
            const collection = findOne('.collection', wrapper)
            collectionManager.addElement(collection)
        })
    })

    findAll('.remove-collection', container).forEach((btn) => {
        on(btn, 'click', (e) => {
            e.stopPropagation()
            e.preventDefault()

            collectionManager.removeElement(btn)
        })
    })
}

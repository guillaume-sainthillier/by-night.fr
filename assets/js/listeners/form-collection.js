import { closest } from '@/js/utils/css'
import { findOne } from '@/js/utils/dom'

/**
 * Symfony form collection buttons: add a prototype-based item or remove the
 * closest one, through the CollectionManager service.
 *
 * @type {Listener}
 */
export default {
    selector: '.add-collection, .remove-collection',
    connect(btn, { app }) {
        const collectionManager = app.get('collectionManager')

        if (btn.matches('.add-collection')) {
            const onAdd = (e) => {
                e.preventDefault()

                const wrapper = closest(btn, '.collection-wrapper')
                const collection = findOne('.collection', wrapper)
                collectionManager.addElement(collection)
            }

            btn.addEventListener('click', onAdd)

            return () => btn.removeEventListener('click', onAdd)
        }

        const onRemove = (e) => {
            e.stopPropagation()
            e.preventDefault()

            collectionManager.removeElement(btn)
        }

        btn.addEventListener('click', onRemove)

        return () => btn.removeEventListener('click', onRemove)
    },
}

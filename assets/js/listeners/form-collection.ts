import type { Listener } from '@silarhi/app-js'
import { findOne } from '@silarhi/app-js'

/**
 * Symfony form collection buttons: add a prototype-based item or remove the
 * closest one, through the CollectionManager service.
 */
export default {
    selector: '.add-collection, .remove-collection',
    connect(btn, { app }) {
        const collectionManager = app.get('collectionManager')

        if (btn.matches('.add-collection')) {
            const onAdd = (e: MouseEvent) => {
                e.preventDefault()

                const wrapper = btn.closest('.collection-wrapper')
                const collection = wrapper && findOne('.collection', wrapper)
                if (!collection) return

                collectionManager.addElement(collection)
            }

            btn.addEventListener('click', onAdd)

            return () => btn.removeEventListener('click', onAdd)
        }

        const onRemove = (e: MouseEvent) => {
            e.stopPropagation()
            e.preventDefault()

            collectionManager.removeElement(btn)
        }

        btn.addEventListener('click', onRemove)

        return () => btn.removeEventListener('click', onRemove)
    },
} satisfies Listener<HTMLElement>

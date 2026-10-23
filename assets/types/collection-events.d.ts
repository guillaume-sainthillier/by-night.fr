import type { CollectionItemDetail } from '@silarhi/app-js/form'

// The events of @silarhi/app-js/form's CollectionManager, dispatched on the collection element (bubbling)
declare global {
    interface HTMLElementEventMap {
        'collection.add': CustomEvent<CollectionItemDetail>
        'collection.added': CustomEvent<CollectionItemDetail>
        'collection.remove': CustomEvent<CollectionItemDetail>
        'collection.removed': CustomEvent<CollectionItemDetail>
    }
}

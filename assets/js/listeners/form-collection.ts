import { defineListener } from '@silarhi/app-js'
import { DEFAULT_SELECTORS } from '@silarhi/app-js/form'

/**
 * The add and remove buttons of the Symfony form collections, handed to the collection manager's own listener
 * (@silarhi/app-js/form). Reached through the service, the manager is only built on the pages with a collection.
 */
export default defineListener<HTMLElement>({
    selector: `${DEFAULT_SELECTORS.add}, ${DEFAULT_SELECTORS.remove}`,
    connect: (button, context) => context.app.get('collectionManager').listener.connect(button, context),
})

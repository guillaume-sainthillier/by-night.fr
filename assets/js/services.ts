import { defineServices, type ServicesOf } from '@silarhi/app-js'
import { CollectionManager } from '@silarhi/app-js/form'
import ModalManager from '@/js/services/modals/ModalManager'
import ToastManager from '@/js/services/modals/ToastManager'

/**
 * The parameters templates/base.html.twig gives the app (`window.appParameters`). A type, not an interface: the app
 * takes them as a `Record<string, unknown>`, which an interface (open to merging) does not satisfy.
 */
export type Parameters = {
    environment: string
    locale: string
    release: string
    dsn: string | null
    user: { id: number } | null
}

export const services = defineServices({
    toastManager: () => new ToastManager(),
    modalManager: () => new ModalManager(),
    // Symfony form collections (templates/form/fields.html.twig), whose add and remove buttons app.ts hands to it with
    // collectionListener(). Reads the container: its return type stops the inference from looping through ServiceMap
    collectionManager: (container): CollectionManager =>
        new CollectionManager({
            app: container.get('app'),
            selectors: { collection: '.collection' },
            // Only remove buttons with a data-confirm-message ask
            confirm: async (message) => {
                const confirmed = await container
                    .get('modalManager')
                    .createConfirm({ text: message, focusConfirm: true, focusCancel: false })

                return confirmed === true
            },
        }),
})

declare module '@silarhi/app-js' {
    interface ServiceMap extends ServicesOf<typeof services> {}
    interface ParameterMap extends Parameters {}
}

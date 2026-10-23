import { defineServices, type ServicesOf } from '@silarhi/app-js'
import type { CollectionManager } from '@silarhi/app-js/form'
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
})

declare module '@silarhi/app-js' {
    interface ServiceMap extends ServicesOf<typeof services> {
        /** Built by app.ts, which registers its listener */
        collectionManager: CollectionManager
    }
    interface ParameterMap extends Parameters {}
}

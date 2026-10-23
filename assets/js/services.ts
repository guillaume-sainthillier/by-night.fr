import type Container from '@/js/services/Container'
import CollectionManager from '@/js/services/form/CollectionManager'
import ModalManager from '@/js/services/modals/ModalManager'
import ToastManager from '@/js/services/modals/ToastManager'

/**
 * The parameters templates/base.html.twig passes to `App.start()`.
 */
export interface Parameters {
    environment: string
    locale: string
    release: string
    dsn: string | null
    user: { id: number } | null
}

interface Services {
    toastManager: ToastManager
    modalManager: ModalManager
    collectionManager: CollectionManager
}

export type Registry = Parameters & Services

export default (di: Container<Registry>) => {
    di.set('toastManager', () => {
        return new ToastManager()
    })

    di.set('modalManager', () => {
        return new ModalManager()
    })

    di.set('collectionManager', () => {
        return new CollectionManager(di.get('modalManager'))
    })
}

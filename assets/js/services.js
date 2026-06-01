import CollectionManager from '@/js/services/form/CollectionManager'
import DisabledManager from '@/js/services/form/DisabledManager'
import ElementManager from '@/js/services/form/ElementManager'
import FormManager from '@/js/services/form/FormManager'
import RequiredManager from '@/js/services/form/RequiredManager'
import VisibleManager from '@/js/services/form/VisibleManager'
import ModalManager from '@/js/services/modals/ModalManager'
import ToastManager from '@/js/services/modals/ToastManager'

/**
 * @param {Container} di
 */
export default (di) => {
    di.set('toastManager', () => {
        return new ToastManager()
    })

    di.set('modalManager', () => {
        return new ModalManager(di)
    })

    di.set('elementManager', () => {
        return new ElementManager()
    })
    di.set('visibleManager', () => {
        return new VisibleManager()
    })

    di.set('disabledManager', () => {
        return new DisabledManager()
    })

    di.set('requiredManager', () => {
        return new RequiredManager()
    })

    di.set('formManager', () => {
        return new FormManager(di.get('visibleManager'), di.get('disabledManager'), di.get('requiredManager'))
    })

    di.set('collectionManager', () => {
        return new CollectionManager(di.get('modalManager'))
    })
}

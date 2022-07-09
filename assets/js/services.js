import ModalManager from './services/modals/ModalManager';
import VisibleManager from './services/form/VisibleManager';
import DisabledManager from './services/form/DisabledManager';
import CollectionManager from './services/form/CollectionManager';
import ElementManager from './services/form/ElementManager';
import FormManager from './services/form/FormManager';
import RequiredManager from './services/form/RequiredManager';
import ToastManager from './services/modals/ToastManager';

/**
 * @param {Container} di
 */
export default (di) => {
    di.set('toastManager', () => {
        return new ToastManager();
    });

    di.set('modalManager', () => {
        return new ModalManager(di);
    });

    di.set('elementManager', () => {
        return new ElementManager();
    });
    di.set('visibleManager', () => {
        return new VisibleManager();
    });

    di.set('disabledManager', () => {
        return new DisabledManager();
    });

    di.set('requiredManager', () => {
        return new RequiredManager();
    });

    di.set('formManager', () => {
        return new FormManager(di.get('visibleManager'), di.get('disabledManager'), di.get('requiredManager'));
    });

    di.set('collectionManager', () => {
        return new CollectionManager(di.get('modalManager'));
    });
};

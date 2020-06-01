import { constructObjectDefinition } from '../../utils/utils';

export default class FormManager {
    /**
     * @param {VisibleManager} visibleManager
     * @param {DisabledManager} disabledManagager
     * @param {RequiredManager} requiredManager
     */
    constructor(visibleManager, disabledManagager, requiredManager) {
        this.visibleManager = visibleManager;
        this.disabledManagager = disabledManagager;
        this.requiredManager = requiredManager;
    }

    handle(formDefinition) {
        formDefinition = constructObjectDefinition(formDefinition);
        if (formDefinition.visibility) {
            this.visibleManager.handle(formDefinition.visibility, false);
        }

        if (formDefinition['!visibility']) {
            this.visibleManager.handle(formDefinition['!visibility'], true);
        }

        if (formDefinition.disable) {
            this.disabledManagager.handle(formDefinition.disable, false);
        }

        if (formDefinition['!disable']) {
            this.disabledManagager.handle(formDefinition['!disable'], true);
        }

        if (formDefinition.require) {
            this.requiredManager.handle(formDefinition.require, false);
        }

        if (formDefinition['!require']) {
            this.requiredManager.handle(formDefinition['!require'], true);
        }
    }
}

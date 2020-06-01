import { updateQueryStringParameter } from '../../utils/utils';

export default class DocumentManager {
    /**
     * @param {FormModalManager} formModalManager
     * @param {UrlLoader} urlLoader
     * @param {ToastManager} toastManager
     * @param {ModalManager} modalManager
     * @param {Translator} translator
     */
    constructor(formModalManager, urlLoader, toastManager, modalManager, translator) {
        this.formModalManager = formModalManager;
        this.urlLoader = urlLoader;
        this.toastManager = toastManager;
        this.modalManager = modalManager;
        this.translator = translator;
    }

    handle(documentsContainer, formLinkedDocuments) {
        if (!documentsContainer) {
            return;
        }

        let currentCategory = '';
        let linkedDocuments = [];

        const newDocument = dom('#new-document');
        if (newDocument) {
            on(newDocument, 'click', (e) => {
                e.preventDefault();
                let url = updateQueryStringParameter(
                    newDocument.getAttribute('href'),
                    'currentCategory',
                    currentCategory
                );
                this.formModalManager.loadModal(url, onDocumentCreated);
            });
        }

        const linkDocument = dom('#link-document');
        if (linkDocument) {
            on(linkDocument, 'click', (e) => {
                e.preventDefault();

                let url = updateQueryStringParameter(
                    linkDocument.getAttribute('href'),
                    'ids',
                    linkedDocuments.join(',')
                );
                url = updateQueryStringParameter(url, 'currentCategory', currentCategory);
                this.formModalManager.loadModal(url, onDocumentCreated);
            });
        }

        on(documentsContainer, 'app.loaded', () => {
            findAll('.edit-document', documentsContainer).forEach((editDocument) => {
                on(editDocument, 'click', (e) => {
                    e.preventDefault();
                    this.formModalManager.loadModal(editDocument.getAttribute('href'), onDocumentEdited);
                });
            });

            findAll('.delete-document', documentsContainer).forEach((deleteDocument) => {
                on(deleteDocument, 'click', (e) => {
                    e.preventDefault();

                    this.modalManager
                        .createConfirm({
                            text: this.translator.trans('label.confirm_action'),
                        })
                        .then((confirmed) => {
                            if (confirmed) {
                                return this.urlLoader.delete(deleteDocument.getAttribute('href'));
                            }
                        })
                        .then((response) => {
                            if (response && response.item) {
                                this.toastManager.createToast('success', response.message);
                                onDocumentDeleted(response.item);
                            }
                        });
                });
            });

            const lines = findAll('.table-documents tbody tr', documentsContainer);
            const filterDocuments = (category) => {
                lines.forEach((line) => {
                    if (category === undefined || data(line, 'category') === category) {
                        show(line);
                    } else {
                        hide(line);
                    }
                });
            };

            findAll('a.nav-link', documentsContainer).forEach((item) => {
                const selectItem = (item) => {
                    removeClass(find('a.nav-link.active', documentsContainer), 'active');
                    addClass(item, 'active');
                    currentCategory = data(item, 'category');
                    filterDocuments(currentCategory);
                };

                on(item, 'click', (e) => {
                    e.preventDefault();
                    selectItem(item);
                });

                if (currentCategory && currentCategory === data(item, 'category')) {
                    selectItem(item);
                }
            });
        });

        const onDocumentCreated = (document) => {
            linkedDocuments.push(document.id);
            formLinkedDocuments.value = linkedDocuments.join(',');
            refreshDocuments();
        };

        const onDocumentEdited = () => {
            refreshDocuments();
        };

        const onDocumentDeleted = (document) => {
            linkedDocuments = linkedDocuments.filter((id) => {
                return id !== document.id;
            });

            refreshDocuments();
        };

        const refreshDocuments = () => {
            this.urlLoader.loadTo(
                updateQueryStringParameter(data(documentsContainer, 'url'), 'ids', linkedDocuments.join(',')),
                documentsContainer
            );
        };
    }
}

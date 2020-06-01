export default (di, container) => {
    const addCollectionBtns = findAll('.add-collection', container);

    if (!addCollectionBtns.length) {
        return;
    }

    const collectionManager = di.get('collectionManager');
    addCollectionBtns.forEach((btn) => {
        on(btn, 'click', (e) => {
            e.preventDefault();

            const wrapper = closest(btn, '.collection-wrapper');
            const collection = find('.form-collection', wrapper);
            collectionManager.addElement(collection);
        });
    });

    findAll('.remove-collection', container).forEach((btn) => {
        on(btn, 'click', (e) => {
            e.preventDefault();
            collectionManager.removeElement(btn);
        });
    });
};

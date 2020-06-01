export default (di, container) => {
    $(container === document ? document.body : container)
        .data('bmd.bootstrapMaterialDesign', null)
        .bootstrapMaterialDesign();
};

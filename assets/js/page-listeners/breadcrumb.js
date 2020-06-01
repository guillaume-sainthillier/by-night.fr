export default () => {
    var bread = $('#bread .breadcrumb');
    var btnCollapse = $('#bread .btn');

    btnCollapse.click(function () {
        bread.toggleClass('collapsed');
        $(this).find('.fa').toggleClass('fa-chevron-down').toggleClass('fa-chevron-right');
    });
};

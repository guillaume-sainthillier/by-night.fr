export default () => {
    const searchForm = $('.navbar .search-form')
    const searchBackdrop = $('#search-menu-backdrop')

    searchForm
        .find('input')
        .focus(function () {
            searchForm.addClass('focus')
            searchBackdrop.addClass('open')
        })
        .blur(function () {
            searchForm.removeClass('focus')
            searchBackdrop.removeClass('open')
        })
}

import $ from 'jquery'
export default (_di, container) => {
    $('label.bmd-label-static', container).each(function () {
        $(this).toggleClass('position-static', $(this).find('.invalid-feedback').length > 0)
    })
}

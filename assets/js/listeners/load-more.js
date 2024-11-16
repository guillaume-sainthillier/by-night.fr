import $ from 'jquery'

export default (di, container) => {
    $('.more', container).click(function (e) {
        e.preventDefault()

        $(this)
            .attr('disabled', true)
            .prepend('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> ')

        const btn = $(this)
        const container = btn.parent().prev()
        $.get($(btn).attr('href'), function (html) {
            const currentContainer = $('<div>').html(html)
            btn.parent().remove()
            currentContainer.insertAfter(container)
            window.App.dispatchPageLoadedEvent(currentContainer[0])
        })
    })
}

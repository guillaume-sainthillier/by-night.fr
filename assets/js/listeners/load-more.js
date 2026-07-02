import $ from 'jquery'

/**
 * Load the next page of results and insert it before the button, then mount
 * the new content.
 */
export default {
    selector: '.more',
    connect(element, { app }) {
        const $element = $(element)

        $element.on('click.loadMore', function (e) {
            e.preventDefault()

            $(this)
                .attr('disabled', true)
                .prepend('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> ')

            const btn = $(this)
            const previousContainer = btn.parent().prev()
            $.get($(btn).attr('href'), (html) => {
                const currentContainer = $('<div>').html(html)
                btn.parent().remove()
                currentContainer.insertAfter(previousContainer)
                app.mount(currentContainer[0])
            })
        })

        return () => $element.off('.loadMore')
    },
}

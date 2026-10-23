import $ from 'jquery'
import type { Listener } from '@/js/types'

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

            // attr('disabled', true) of jQuery: the boolean attribute set to its name
            $(this)
                .attr('disabled', 'disabled')
                .prepend('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> ')

            const btn = $(this)
            const previousContainer = btn.parent().prev()
            // Without an href, jQuery reads the current page, as it does for an empty URL
            $.get($(btn).attr('href') ?? '', (html: string) => {
                const currentContainer = $('<div>').html(html)
                btn.parent().remove()
                currentContainer.insertAfter(previousContainer)
                app.mount(currentContainer[0])
            })
        })

        return () => $element.off('.loadMore')
    },
} satisfies Listener

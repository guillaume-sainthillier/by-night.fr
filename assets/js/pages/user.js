import { Tab } from '@tabler/core/dist/js/tabler.esm'
import $ from 'jquery'
import { iconHtml } from '@/js/components/icons'
import Loader2Icon from '@/js/icons/lucide/Loader2'

/** @type {Page} */
function initialize({ app }) {
    init()

    function init() {
        initTabFromHash()
        initLoadMoreEvents()
    }

    // A link to a tab opens it: "Mes sorties" leads to the past ones as #passes
    function initTabFromHash() {
        const { hash } = window.location
        const trigger = hash && document.querySelector(`[data-bs-toggle="tab"][href="${CSS.escape(hash)}"]`)
        if (trigger) {
            Tab.getOrCreateInstance(trigger).show()
        }
    }

    function initLoadMoreEvents() {
        $('.user-events-container').each(function () {
            const container = $(this)
            bindLoadMore(container)
        })
    }

    function bindLoadMore(container) {
        container
            .find('.load-more')
            .off('click')
            .on('click', function (e) {
                e.preventDefault()

                const loadMore = $(this)
                const btn = loadMore.find('.btn')

                // Add spinner to button
                const originalText = btn.html()
                btn.html(`${iconHtml(Loader2Icon, 'icon-spin')} ${originalText}`)
                btn.prop('disabled', true)

                $.get(loadMore.data('url'), (html) => {
                    // Remove the load-more button
                    loadMore.remove()

                    // Append new content
                    const $newContent = $(html)
                    container.append($newContent)

                    // Re-bind load-more on the new content
                    bindLoadMore(container)

                    // Re-initialize any page listeners on new event cards
                    app.mount(container[0])
                }).fail(() => {
                    // Restore button on error
                    btn.html(originalText)
                    btn.prop('disabled', false)
                })
            })
    }
}

window.App.registerPage('user', initialize)

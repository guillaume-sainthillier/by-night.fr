import { definePage } from '@silarhi/app-js'
import { renderIcon } from '@silarhi/ux-icons-js'
import { Tab } from '@tabler/core'
import $ from 'jquery'
import { LucideLoader2Icon } from '@/js/icons'

export default definePage(({ app }) => {
    init()

    function init() {
        initTabFromHash()
        initLoadMoreEvents()
    }

    // A link to a tab opens it: "Mes sorties" leads to the past ones as #passes
    function initTabFromHash() {
        const { hash } = window.location
        const trigger =
            hash && document.querySelector<HTMLElement>(`[data-bs-toggle="tab"][href="${CSS.escape(hash)}"]`)
        if (trigger) {
            const tab = Tab.getOrCreateInstance(trigger)
            if (tab instanceof Tab) {
                tab.show()
            }
        }
    }

    function initLoadMoreEvents() {
        $('.user-events-container').each((_index, element) => {
            const container = $(element)
            bindLoadMore(container)
        })
    }

    function bindLoadMore(container: JQuery) {
        container
            .find('.load-more')
            .off('click')
            .on('click', (e) => {
                e.preventDefault()

                const loadMore = $(e.currentTarget)
                const btn = loadMore.find('.btn')

                // Add spinner to button
                const originalText = btn.html()
                btn.html(`${renderIcon(LucideLoader2Icon, { class: 'icon icon-spin' })} ${originalText}`)
                btn.prop('disabled', true)

                $.get(loadMore.data('url'), (html: string) => {
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
})

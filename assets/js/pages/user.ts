import { definePage } from '@silarhi/app-js'
import { getHtml } from '@silarhi/app-js/http'
import { renderIcon } from '@silarhi/ux-icons-js'
import { Tab } from '@tabler/core'
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
        for (const container of document.querySelectorAll<HTMLElement>('.user-events-container')) {
            bindLoadMore(container)
        }
    }

    // The container's load-more button: the last page brings the next one, if any
    function bindLoadMore(container: HTMLElement) {
        const loadMore = container.querySelector<HTMLElement>('.load-more')
        loadMore?.addEventListener('click', async (e) => {
            e.preventDefault()

            const btn = loadMore.querySelector<HTMLButtonElement>('.btn')
            if (btn?.disabled) {
                return
            }

            // Add spinner to button
            const originalText = btn?.innerHTML ?? ''
            if (btn) {
                btn.innerHTML = `${renderIcon(LucideLoader2Icon, { class: 'icon icon-spin' })} ${originalText}`
                btn.disabled = true
            }

            let html: string
            try {
                html = await getHtml(loadMore.dataset.url ?? '')
            } catch {
                // Restore button on error
                if (btn) {
                    btn.innerHTML = originalText
                    btn.disabled = false
                }
                return
            }

            // Remove the load-more button, then append new content
            loadMore.remove()
            container.insertAdjacentHTML('beforeend', html)

            // Re-bind load-more on the new content
            bindLoadMore(container)

            // Re-initialize any page listeners on new event cards
            app.mount(container)
        })
    }
})

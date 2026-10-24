import type { Listener } from '@silarhi/app-js'

/**
 * Show a bar pinned to the bottom of a phone's screen (`.action-bar`) once the element its `data-action-bar` names has
 * scrolled away above the screen: the event page's booking button, which the bar repeats. Back at it, the bar hides.
 */
export default {
    selector: '[data-action-bar]',
    connect(bar) {
        const target = bar.dataset.actionBar ? document.querySelector(bar.dataset.actionBar) : null
        if (!target) {
            return
        }

        const observer = new IntersectionObserver(([entry]) => {
            bar.classList.toggle('is-visible', !entry.isIntersecting && entry.boundingClientRect.bottom < 0)
        })
        observer.observe(target)

        return () => observer.disconnect()
    },
} satisfies Listener<HTMLElement>

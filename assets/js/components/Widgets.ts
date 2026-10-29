import { getHtml } from '@/js/utils/http'

export default class Widgets {
    init(container: ParentNode = document) {
        this.initMoreWidgets(container.querySelectorAll<HTMLElement>('.widget'))
    }

    // Deps: ['scrollable']
    initMoreWidgets(widgets: Iterable<HTMLElement>) {
        for (const container of widgets) {
            const containerActions = container.querySelector('.more-container')
            const moreContentLink = container.querySelector<HTMLElement>('.more-content')
            const scrollArea = container.querySelector<HTMLElement>('.scroll-area')
            const containerBody = container.querySelector<HTMLElement>('.scroll-area-content') ?? scrollArea

            if (!containerActions) {
                continue
            }

            if (!moreContentLink) {
                containerActions.remove()
                continue
            }

            // The link moves into the actions, alone
            containerActions.replaceChildren(moreContentLink)

            // The block is either the button itself or a wrapper (progress, counter) around it
            const moreButton = moreContentLink.matches('button')
                ? moreContentLink
                : moreContentLink.querySelector('button')
            moreButton?.addEventListener('click', async (e) => {
                e.preventDefault()
                if (moreButton.classList.contains('disabled')) {
                    return
                }

                moreButton.classList.add('disabled')
                moreButton.insertAdjacentHTML(
                    'afterbegin',
                    '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> '
                )
                const items = containerBody?.querySelectorAll('.scroll-item')
                const scrollAreaLastItem = items?.[items.length - 1]
                let content: string
                try {
                    content = await getHtml(moreContentLink.dataset.href ?? '')
                } catch {
                    // The button is offered again
                    moreButton.querySelector('.spinner-border')?.remove()
                    moreButton.classList.remove('disabled')
                    window.App.get('toastManager').createToast('error', "La suite n'a pas pu être chargée.")
                    return
                }
                moreContentLink.remove()
                containerBody?.insertAdjacentHTML('beforeend', content)
                this.initMoreWidgets([container])
                window.App.mount(container)
                const firstNewItem = scrollAreaLastItem?.nextElementSibling
                if (firstNewItem instanceof HTMLElement && scrollArea) {
                    this.scrollTo(firstNewItem, scrollArea)
                }
            })
        }
    }

    /** Scrolls `container` until `elem` is at its start */
    scrollTo(elem: HTMLElement, container: HTMLElement) {
        const offset = elem.getBoundingClientRect()
        const origin = container.getBoundingClientRect()
        // A row of cards scrolls sideways on a phone (.row-scroll-mobile) and wraps on wider screens
        if (container.classList.contains('scroll-area-horizontal') || container.scrollWidth > container.clientWidth) {
            container.scrollBy({ left: offset.left - origin.left + 1, behavior: 'smooth' })
        } else {
            container.scrollBy({ top: offset.top - origin.top, behavior: 'smooth' })
        }
    }
}

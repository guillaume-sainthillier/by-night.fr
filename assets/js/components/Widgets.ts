import $ from 'jquery'

export default class Widgets {
    init(selector?: JQuery.Selector | Element | JQuery) {
        $(document).ready(() => {
            this.initMoreWidgets($('.widget', selector || document))
        })
    }

    // Deps: ['scrollable']
    initMoreWidgets(elems: JQuery) {
        elems.each((_index, element) => {
            const container = $(element)
            const containerActions = container.find('.more-container')
            const moreContentLink = container.find('.more-content')
            const scrollArea = container.find('.scroll-area')
            const containerBody = container.find('.scroll-area-content').length
                ? container.find('.scroll-area-content')
                : scrollArea

            if (!containerActions.length) {
                return
            }

            if (!moreContentLink.length) {
                containerActions.remove()
            } else {
                const newMoreContentLink = moreContentLink.clone()
                // What html() does with a collection
                containerActions.empty().append(newMoreContentLink)
                moreContentLink.remove()

                // The block is either the button itself or a wrapper (progress, counter) around it
                const moreButton = newMoreContentLink.is('button')
                    ? newMoreContentLink
                    : newMoreContentLink.find('button')
                moreButton.off('click').click((e) => {
                    moreButton
                        .addClass('disabled')
                        .prepend(
                            '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> '
                        )
                    const scrollAreaLastItem = containerBody.find('.scroll-item').last()
                    $.get(newMoreContentLink.data('href')).done((content: string) => {
                        newMoreContentLink.remove()
                        containerBody.append(content)
                        this.initMoreWidgets(container)
                        window.App.mount(container[0])
                        if (scrollAreaLastItem.next().length > 0) {
                            this.scrollTo(scrollAreaLastItem.next(), scrollArea, () => {})
                        }
                    })

                    e.preventDefault()
                    return false
                })
            }
        })
    }

    scrollTo(elem: JQuery, container: JQuery, callback: () => void) {
        let options: JQuery.PlainObject<number>
        // A row of cards scrolls sideways on a phone (.row-scroll-mobile) and wraps on wider screens
        if (container.hasClass('scroll-area-horizontal') || container[0].scrollWidth > container[0].clientWidth) {
            options = {
                scrollLeft: ($(container).scrollLeft() ?? 0) + elem.position().left - $(container).position().left + 1,
            }
        } else {
            options = { scrollTop: ($(container).scrollTop() ?? 0) + elem.position().top }
        }

        $(container).animate(options, 800, callback)
    }
}

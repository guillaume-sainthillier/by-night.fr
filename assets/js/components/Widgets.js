import $ from 'jquery'

export default class Widgets {
    init(selector) {
        $(document).ready(() => {
            this.initMoreWidgets($('.widget', selector || document))
        })
    }

    // Deps: ['scrollable']
    initMoreWidgets(elems) {
        const self = this
        elems.each(function () {
            const container = $(this)
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
                containerActions.html(newMoreContentLink)
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
                    $.get(newMoreContentLink.data('href')).done((content) => {
                        newMoreContentLink.remove()
                        containerBody.append(content)
                        self.initMoreWidgets(container)
                        window.App.mount(container[0])
                        if (scrollAreaLastItem.next().length > 0) {
                            self.scrollTo(scrollAreaLastItem.next(), scrollArea, () => {})
                        }
                    })

                    e.preventDefault()
                    return false
                })
            }
        })
    }

    scrollTo(elem, container, callback) {
        let options
        // A row of cards scrolls sideways on a phone (.row-scroll-mobile) and wraps on wider screens
        if (container.hasClass('scroll-area-horizontal') || container[0].scrollWidth > container[0].clientWidth) {
            options = {
                scrollLeft: $(container).scrollLeft() + elem.position().left - $(container).position().left + 1,
            }
        } else {
            options = { scrollTop: $(container).scrollTop() + elem.position().top }
        }

        $(container).animate(options, 800, callback)
    }
}

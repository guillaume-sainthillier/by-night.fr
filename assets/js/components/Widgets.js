import $ from 'jquery'

export default class Widgets {
    init(selector) {
        const self = this
        $(document).ready(function () {
            self.initMoreWidgets($('.widget', selector || document))
        })
    }

    // Deps: ['scrollable']
    initMoreWidgets(elems) {
        const self = this
        elems.each(function () {
            const container = $(this)
            const containerActions = container.find('.more-container')
            const moreContentLink = container.find('a.more-content')
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

                newMoreContentLink.off('click').click(function (e) {
                    const btn = $(this)
                    btn.addClass('disabled').prepend(
                        '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> '
                    )
                    const scrollAreaLastItem = containerBody.find('.scroll-item').last()
                    $.get(btn.attr('href')).done(function (content) {
                        btn.remove()
                        containerBody.append(content)
                        self.initMoreWidgets(container)
                        window.App.dispatchPageLoadedEvent(container[0])
                        if (scrollAreaLastItem.next().length > 0) {
                            self.scrollTo(scrollAreaLastItem.next(), scrollArea, function () {})
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
        if (container.hasClass('scroll-area-horizontal')) {
            options = {
                scrollLeft: $(container).scrollLeft() + elem.position().left - $(container).position().left + 1,
            }
        } else {
            options = { scrollTop: $(container).scrollTop() + elem.position().top }
        }

        $(container).animate(options, 800, callback)
    }
}

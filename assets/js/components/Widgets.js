export default class Widgets {
    init(selecteur) {
        const self = this;
        $(function () {
            self.initMoreWidgets($('.widget', selecteur || document));
        });
    }

    //Deps: ['scrollable']
    initMoreWidgets(elems) {
        const self = this;
        elems.each(function () {
            var container = $(this);
            var containerActions = container.find('.more-container');
            var moreContentLink = container.find('a.more-content');
            var scrollArea = container.find('.scroll-area');
            var containerBody = container.find('.scroll-area-content').length
                ? container.find('.scroll-area-content')
                : scrollArea;

            if (!containerActions.length) {
                return;
            }

            if (!moreContentLink.length) {
                containerActions.remove();
            } else {
                var newMoreContentLink = moreContentLink.clone();
                containerActions.html(newMoreContentLink);
                moreContentLink.remove();

                newMoreContentLink.off('click').click(function (e) {
                    var btn = $(this);
                    btn.addClass('disabled').prepend(
                        '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> '
                    );
                    var scrollAreaLastItem = containerBody.find('.scroll-item').last();
                    $.get(btn.attr('href')).done(function (content) {
                        btn.remove();
                        containerBody.append(content);
                        self.initMoreWidgets(container);
                        App.dispatchPageLoadedEvent(container[0]);
                        if (scrollAreaLastItem.next().length > 0) {
                            self.scrollTo(scrollAreaLastItem.next(), scrollArea, function () {});
                        }
                    });

                    e.preventDefault();
                    return false;
                });
            }
        });
    }

    scrollTo(elem, container, callback) {
        if (container.hasClass('scroll-area-horizontal')) {
            var options = {
                scrollLeft: $(container).scrollLeft() + elem.position().left - $(container).position().left + 1,
            };
        } else {
            var options = { scrollTop: $(container).scrollTop() + elem.position().top };
        }

        $(container).animate(options, 800, callback);
    }
}

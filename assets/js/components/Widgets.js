import 'iscroll/build/iscroll';

export default class Widgets {
    constructor() {
        this.scrollMap = [];
    }

    init(selecteur) {
        const self = this;
        $(function () {
            self.initMoreWidgets($('.widget', selecteur || document));
            self.initScrollable(selecteur);
        });
    }

    //Deps: ['scrollable']
    initScrollable(selecteur) {
        const self = this;
        return;
        $(".scrollable", selecteur || document).each(function () {
            if (!$(this).attr('id')) {
                $(this).attr('id', 'scroll-' + Math.floor(Math.random() * 100000));
            }

            var id = $(this).attr('id');
            if (!self.scrollMap[id]) {
                self.scrollMap[id] = new IScroll("#" + id, {
                    scrollbars: true,
                    mouseWheel: true,
                    interactiveScrollbars: true,
                    shrinkScrollbars: false,
                    fadeScrollbars: true
                });

                self.scrollMap[id].on('scrollStart', function () {
                    App.initLazyLoading($("#" + id));
                });

                self.scrollMap[id].on('scrollEnd', function () {
                    App.initLazyLoading($("#" + id));
                });
            }
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
            var containerBody = container.find('.scroll-area-content').length ? container.find('.scroll-area-content') : scrollArea;

            if (!containerActions.length) {
                return;
            }

            if (!moreContentLink.length) {
                containerActions.remove();
            } else {
                var newMoreContentLink = moreContentLink.clone();
                containerActions.html(newMoreContentLink);
                moreContentLink.remove();

                newMoreContentLink.unbind('click').click(function (e) {
                    var btn = $(this);
                    btn.addClass("disabled").prepend('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> ');
                    var scrollAreaLastItem = containerBody.find('.scroll-item').last();
                    $.get(btn.attr('href')).done(function (content) {
                        btn.remove();
                        containerBody.append(content);
                        self.initMoreWidgets(container);
                        App.initComponents(container);
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
            var options = {'scrollLeft': $(container).scrollLeft() + elem.offset().left};
        } else {
            var options = {'scrollTop': $(container).scrollTop() + elem.position().top};
        }

        console.log(options, elem, container);
        $(container).animate(options, 800, callback);
    }
}
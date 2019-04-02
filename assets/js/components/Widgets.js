var Widgets = {
    scrollMap: [],

    init: function (selecteur) {
        $(function () {
            Widgets.initMoreWidgets($('.widget', selecteur || document));
            Widgets.initScrollable(selecteur);
        });
    },
    //Deps: ['scrollable']
    initScrollable: function (selecteur) {
        $(".scrollable", selecteur || document).each(function () {
            if (!$(this).attr('id')) {
                $(this).attr('id', 'scroll-' + Math.floor(Math.random() * 100000));
            }

            var id = $(this).attr('id');
            if (!Widgets.scrollMap[id]) {
                Widgets.scrollMap[id] = new IScroll("#" + id, {
                    scrollbars: true,
                    mouseWheel: true,
                    interactiveScrollbars: true,
                    shrinkScrollbars: false,
                    fadeScrollbars: true
                });

                Widgets.scrollMap[id].on('scrollStart', function () {
                    App.initLazyLoading($("#" + id));
                });

                Widgets.scrollMap[id].on('scrollEnd', function () {
                    App.initLazyLoading($("#" + id));
                });
            }
        });

    },
    //Deps: ['scrollable']
    initMoreWidgets: function (elems) {
        elems.each(function () {
            var container = $(this);
            var containerActions = container.find('.panel-footer');
            var moreContentLink = container.find('.more-content');
            var containerBody = moreContentLink.parent();

            if(! containerActions.length) {
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
                    if (btn.attr('disabled')) {
                        return false;
                    }
                    btn.attr("disabled", true).prepend("<i class='fa fa-spin fa-spinner'></i> ");
                    $.get(btn.attr('href')).done(function (content) {
                        btn.remove();
                        containerBody.append(content);

                        var scroll = Widgets.scrollMap[container.find('.scrollable').attr('id')];
                        if (scroll) {
                            scroll.refresh();
                            scroll.scrollTo(0, scroll.maxScrollY, 0, IScroll.utils.ease.elastic);
                        }
                        Widgets.initMoreWidgets(container);
                        App.initComponents(container);
                    });

                    e.preventDefault();
                    return false;
                });
            }
        });
    }
};

Widgets.init();

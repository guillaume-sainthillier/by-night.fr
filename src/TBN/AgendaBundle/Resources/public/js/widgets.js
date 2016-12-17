var Widgets = {
    init: function (selecteur) {
        $(function () {
            Widgets.initMoreWidgets($('.widget', selecteur || document));
            Widgets.initScrollable(selecteur);
        });
    },
    //Deps: ['scrollable']
    initScrollable: function (selecteur) {
        var targets = $(".scrollable", selecteur || document);
        if (targets.length) {
            targets.mCustomScrollbar({
                axis: "y",
                theme: "minimal-dark",
                autoHideScrollbar: true
            });
        }
    },
    //Deps: ['scrollable']
    initMoreWidgets: function (elems) {
        elems.each(function () {
            var container = $(this);
            var containerActions = container.find('.panel-footer');
            var moreContentLink = container.find('.more-content');
            var containerBody = moreContentLink.parent();

            if (!moreContentLink.length) {
                containerActions.html('');
            } else {
                var newMoreContentLink = moreContentLink.clone();
                containerActions.html(newMoreContentLink);
                moreContentLink.remove();

                newMoreContentLink.unbind('click').click(function (e) {
                    var btn = $(this);
                    if(btn.attr('disabled')) {
                        return false;
                    }
                    btn.attr("disabled", true).prepend("<i class='fa fa-spin fa-spinner'></i> ");
                    $.get(btn.attr('href')).done(function (content) {
                        btn.remove();
                        console.log(containerBody);
                        containerBody.append(content);
                        container.find('.scrollable').mCustomScrollbar("scrollTo", "bottom");
                        Widgets.initMoreWidgets(container);
                    });

                    e.preventDefault();
                    return false;
                });
            }
        });
    }
};

Widgets.init();
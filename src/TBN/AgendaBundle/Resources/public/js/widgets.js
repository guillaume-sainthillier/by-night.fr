var Widgets = {
    init: function (selecteur) {
        $(function () {
            Widgets.initMoreWidgets(selecteur);
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
    initMoreWidgets: function (selecteur) {
        $(".more-widget", selecteur || document).each(function () {
            var that = $(this);
            var page = 2;
            $(this).click(function (e) {
                var container = that.closest('.widget').find(".more-content");
                that.attr("disabled", true).prepend("<i class='fa fa-spin fa-spinner'></i> ");
                $.get(that.data("href") + '/' + page).done(function (content) {
                    container.replaceWith(content);
                    that.closest('.widget').find('.scrollable').mCustomScrollbar("scrollTo", "bottom");

                    if (that.closest('.widget').find('.more-content').length) {
                        that.attr("disabled", false).find(".fa").remove();
                        page++;
                    } else {
                        that.closest('.panel-footer').remove();
                    }
                });

                e.preventDefault();
                return false;
            });
        });
    }
};

Widgets.init();
define(function (require)
{
    var $ = require('jquery');

    return {
        init: function(selecteur)
        {
            var that = this;
            $(function()
            {
                that.initMoreWidgets(selecteur);
                that.initScrollable(selecteur);
            });
        },
        
        //Deps: ['scrollable']
        initScrollable: function(selecteur)
        {
            var targets = $(".scrollable", selecteur || document);
            if (targets.length)
            {
                console.log("mCustomScrollbar LOADING");
                require('scrollable');
                console.log("mCustomScrollbar LOADED");
                targets.mCustomScrollbar({
                    axis: "y",
                    theme: "minimal-dark",
                    autoHideScrollbar: true
                });
            }
        },
        
        //Deps: ['scrollable']
        initMoreWidgets: function(selecteur)
        {
            $(".more-widget", selecteur || document).each(function ()
            {
                var that = $(this);
                var page = 2;
                $(this).click(function (e)
                {
                    var container = that.closest('.widget').find(".more-content");
                    that.attr("disabled", true).prepend("<i class='fa fa-spin fa-spinner'></i> ");
                    $.get(that.data("href") + '/' + page).done(function (content)
                    {
                        container.replaceWith(content);
                        require('scrollable');
                        that.closest('.widget').find('.scrollable').mCustomScrollbar("scrollTo", "bottom");

                        if (that.closest('.widget').find('.more-content').length)
                        {
                            that.attr("disabled", false).find(".fa").remove();
                            page++;
                        } else
                        {
                            that.closest('.panel-footer').remove();
                        }
                    });

                    e.preventDefault();
                    return false;
                });
            });
        }
    };
});


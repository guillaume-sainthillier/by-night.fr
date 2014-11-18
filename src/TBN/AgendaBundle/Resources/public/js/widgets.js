$(document).ready(function() {
    init_more_widgets();
    init_scrollable();
});

function init_scrollable()
{
    $(".scrollable").mCustomScrollbar({
        axis: "y",
        theme: "dark-thin",
        autoHideScrollbar: true
    });
}

function init_more_widgets()
{
    $(".more-widget").each(function ()
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
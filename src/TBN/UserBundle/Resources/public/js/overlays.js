function init_overlays()
{
    var config = {
        "selector_item" : ".item > .picture",
        "selector_picture" : ".project > .picture",
        "selector_overlay" : ".overlay",
        "selector_search" : ".search",
        "selector_link" : ".link",
        "opacity_begin" : 0,
        "opacity_end" : 1,        
        "margin_right_end" : "48%",
        "margin_right_begin" : "0px",
        "margin_top_begin" : "0px",
        "margin_top_end" : "100%",
        "duration_primary" : 100,
        "duration_bis" : 250        
    };
    
    if((navigator.userAgent.match(/iPhone/i)) || (navigator.userAgent.match(/iPod/i)) || (navigator.userAgent.match(/iPad/i))) {

        $(config.selector_picture).click(function()
        {
            $(config.selector_picture).each(function()
            {
                //$(this).find(config.selector_overlay).fadeOut().parent().find(config.selector_search).animate({marginLeft: '0px'},config.duration_bis);
                $(this).find(config.selector_overlay).fadeOut().parent().find(config.selector_link).animate({marginRight: config.margin_right_begin},config.duration_bis);
            });

            //$(this).find(config.selector_overlay).fadeIn().parent().find(config.selector_search).animate({marginLeft: '48%'},config.duration_bis);
            $(this).find(config.selector_overlay).fadeIn().parent().find(config.selector_link).animate({marginRight: config.margin_right_end},config.duration_bis);
        });

        $(config.selector_item).click(function()
        {
            $(config.selector_item).each(function()
            {
                    $(this).find(config.selector_overlay).animate({opacity: config.opacity_begin, marginTop: config.margin_top_end}, config.duration_default);
            });

            $(this).find(config.selector_overlay).animate({opacity: config.opacity_end, marginTop: config.margin_top_begin}, config.duration_default);
        });

    } else 
    {
        $(config.selector_picture).hover(
            function(){
                //$(this).find(config.selector_overlay).fadeIn().parent().find(config.selector_search).animate({marginLeft: '48%'},config.duration_bis);
                $(this).find(config.selector_overlay).fadeIn().parent().find(config.selector_link).animate({marginRight: config.margin_right_end},config.duration_bis);
            },
            function(){
                //$(this).find(config.selector_overlay).fadeOut().parent().find(config.selector_search).animate({marginLeft: '0px'},config.duration_bis);
                $(this).find(config.selector_overlay).fadeOut().parent().find(config.selector_link).animate({marginRight: config.margin_right_begin},config.duration_bis);
            }
        );

        $(config.selector_item).hover(
            function(){
                $(this).find(config.selector_overlay).animate({opacity: config.opacity_end, marginTop: config.margin_top_begin}, config.duration_default);
            },
            function(){
                $(this).find(config.selector_overlay).animate({opacity: config.opacity_begin, marginTop: config.margin_top_end}, config.duration_default);
            }
        );
    }
}
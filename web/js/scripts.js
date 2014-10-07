window.parent_elem = null;
window.last_fb_width = null;

$(document).ready(function()
{
    init_js_components();
    init_scrollTo();
    init_hide_menu_on_scroll();
    init_autofocus();
    init_social_counts();
    init_heights();
});

function init_heights()
{
    $("#content").css("min-height", $("#widgets").height() + 50);
}

function init_social_counts()
{
    if ($("#footer .social").length)
    {
        $.get(window.get_social_count).done(function(counts)
        {
            $.each(counts, function(social, count)
            {
                $(".social." + social).find(".count").text(count);
            });
        });
    }
}

function init_autofocus(selecteur)
{
    $("[autofocus]", selecteur || document).focus();
}

function init_hide_menu_on_scroll()
{
    $(window).scroll(function()
    {
        $(".navbar-toggle").each(function()
        {
            var href = $(this).data("target");
            var elem = $(href);
            if (elem.length && elem.hasClass("in"))
            {
                elem.removeClass("in");
            }
        });
    });
}

function init_js_components(selecteur)
{
    init_connexion(selecteur);
    init_register(selecteur);
    init_tooltips(selecteur);
    init_participer(selecteur);
    init_autofocus(selecteur);
}

function init_participer(selecteur)
{
    var options = {
        "css_selecteur_participer": ".btn.participer",
        "css_selecteur_interesser": ".btn.interesser",
        "css_active_class": "active",
        "css_buttons": ".buttons",
        "css_hidden": "hidden",
        "css_selecteur_icon": ".check"
    };

    $(options.css_selecteur_participer + ", " + options.css_selecteur_interesser, selecteur || document).unbind("click").click(function()
    {
        var btn = $(this);

        if (btn.hasClass(options.css_active_class))
        {
            return false;
        }

        btn.data("loading-text", btn.text()).button("loading");
        $.post(btn.data("href")
                ).done(function(msg)
        {
            if (msg.success)
            {
                btn.button("reset");
                if (msg.participer)//L'utilisateur participe
                {
                    $(options.css_selecteur_interesser).removeClass(options.css_active_class).find(options.css_selecteur_icon).addClass(options.css_hidden);
                    $(options.css_selecteur_participer).addClass(options.css_active_class).find(options.css_selecteur_icon).removeClass(options.css_hidden);

                }

                if (msg.interet)//L'utilisateur est interessé
                {
                    $(options.css_selecteur_participer).removeClass(options.css_active_class).find(options.css_selecteur_icon).addClass(options.css_hidden);
                    $(options.css_selecteur_interesser).addClass(options.css_active_class).find(options.css_selecteur_icon).removeClass(options.css_hidden);
                }
            }
        });
    }).each(function()
    {
        if ($(this).hasClass(options.css_active_class))//L'utilisateur est interessé
        {
            $(this).find(options.css_selecteur_icon).removeClass(options.css_hidden);
        } else
        {
            $(this).find(options.css_selecteur_icon).addClass(options.css_hidden);
        }
    }).closest(options.css_buttons).removeClass(options.css_hidden);
}

function init_tooltips(selecteur)
{
    $(".tbn_tooltip", selecteur || document).tooltip();
}

/**
 * 
 * Code created by lovely http://www.php.net/
 * 
 */
function init_scrollTo()
{
    var settings = {
        text: 'En haut',
        min: 200,
        inDelay: 600,
        outDelay: 400,
        containerID: 'toTop',
        containerHoverID: 'toTopHover',
        scrollSpeed: 400,
        easingType: 'linear'
    };

    var toTopHidden = true;
    var toTop = $('#' + settings.containerID);

    toTop.click(function(e) {
        e.preventDefault();
        $.scrollTo(0, settings.scrollSpeed, {easing: settings.easingType});
    });

    $(window).scroll(function() {
        var sd = $(this).scrollTop();
        if (sd > settings.min && toTopHidden)
        {
            toTop.fadeIn(settings.inDelay);
            toTopHidden = false;
        }
        else if (sd <= settings.min && !toTopHidden)
        {
            toTop.fadeOut(settings.outDelay);
            toTopHidden = true;
        }
    });
}

function popup(href, parent, width, height, left, top)
{
    window.parent_elem = parent;

    width = width || 800,
            height = height || 500,
            left = left || ($(window).width() / 2) - (width / 2),
            top = top || ($(window).height() / 2) - (height / 2);

    window.open(href, null, 'width=' + width + ',height=' + height + ',toolbar=0,menubar=0,location=0,status=0,scrollbars=1,resizable=1,left=' + left + ',top=' + top + ',toolbar=0');
}

function init_register(selecteur)
{
    $(".register", selecteur || document).unbind("click").click(function()
    {
        var $dialog = $("#dialog_details");
        $dialog.modal("show").modal("loading").load($(this).attr("href"), function()
        {
            handle_register($dialog);
        });

        return false;
    });
}

function handle_register($dialog)
{
    init_js_components($dialog);
    $dialog.find("form").unbind("submit").submit(function()
    {
        var href = $(this).attr("action");
        var datas = $(this).serialize();
        var submit_button = $("#_register");
        submit_button.button("loading");
        $.post(href, datas).done(function(data)
        {
            submit_button.button("reset");

            if (typeof data.success === "boolean" && data.success)
            {
                $dialog.modal("hide");
                location.reload();
            } else
            {
                $dialog.html(data);
                handle_register($dialog); //ne rien mettre aprÃ¨s
            }
        });
        return false;
    });
}

function loading_buttons(selecteur)
{
    $('.btn-submit', selecteur || document).button('loading');

}

function reset_buttons(selecteur)
{
    $('.btn-submit', selecteur || document).button('reset');

}

function init_alerts(selecteur)
{
    $(".alert", selecteur || document).alert();
}

function init_connexion(selecteur)
{
    $(".connexion", selecteur || document).unbind("click").click(function(e)
    {
        var $dialog = $("#dialog_details");
        $dialog.modal("show").modal("loading").load($(this).attr("href"), function()
        {
            init_js_components($dialog);
            $dialog.find("form").unbind("submit").submit(function()
            {
                var href = $(this).attr("action");
                var datas = $(this).serialize();
                var submit_button = $("#_submit");
                submit_button.button("loading");
                $.post(href, datas).done(function(data)
                {
                    submit_button.button("reset");
                    if (!data.success)
                    {
                        $dialog.modal("setLittleErreur", data.message);
                    } else
                    {
                        $dialog.modal("hide");
                        location.reload();
                    }
                });
                return false;
            });
        });

        return false;
    });
}

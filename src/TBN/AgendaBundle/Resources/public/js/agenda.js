SocialSDK.facebook().twitter();

$(function ()
{
    init_criteres();
    init_shorcut_date();
    init_pagination();
    init_soirees();
});

function init_soirees(selector)
{
    init_unveil(selector);
    init_fancybox(selector);
    init_pagination_links();
}

function init_pagination_links(selector)
{
    $(".pagination li", selector || document).click(function (e)
    {
        var link = $(this).find("a").attr("href");
        $("form[name='tbn_search_agenda']").attr("action", link).submit();
        e.preventDefault();
        return false;
    });
}

//Deps: ['pjax']
function init_pagination(selecteur)
{
    var selector = '#pjax-search-container';
    $(selecteur || document).on('submit', 'form[data-pjax]', function (event) {
        $.pjax.submit(event, selector);
    });
    $(selecteur || document).on('pjax:success', function () {
        init_soirees($(selector));
    });
}

/**
 * 
 * @returns {undefined}
 */
function init_criteres()
{
    var options = {
        "css_hidden": "cache",
        "css_initial_hidden": "hidden",
        "selector_btn_criteres": ".btn_criteres",
        "selector_block_criteres": ".criteres",
        "selector_main_block": ".block_criteres",
        "duration": 300
    };
    
    //Bon bloc indigeste :)
    $(options.selector_btn_criteres).click(function ()
    {
        var div_criteres = $(this).closest(options.selector_main_block).find(options.selector_block_criteres);
        if (div_criteres.hasClass(options.css_hidden))
        {
            div_criteres.show(options.duration, function ()
            {
                $(this).removeClass(options.css_hidden);
            });
        } else
        {
            div_criteres.hide(options.duration, function ()
            {
                $(this).addClass(options.css_hidden);
            });
        }
    })
    .closest(options.selector_main_block)
    .find(options.selector_block_criteres)
    .hide()
    .removeClass(options.css_initial_hidden)
    .addClass(options.css_hidden);
}

/**
 * Initialise le lazy loading des images
 * @param {type} selecteur
 * @returns {undefined}
 */
function init_unveil(selecteur)
{
    $(".img", selecteur || document).unveil(200, function ()
    {
        $(this).removeClass("loading");
    });
}

/**
 * Initialise les boutons WE, cette semaine et ce mois
 * @returns {undefined}
 */
function init_shorcut_date()
{
    $("select.shorcuts_date").unbind("change").change(function ()
    {
        var selected = $(this).find("option:selected");
        $("#tbn_search_agenda_du").val(selected.data("date-debut") || "");
        $("#tbn_search_agenda_au").val(selected.data("date-fin") || "");
    });
}


/**
 * Initialise les fancybox
 * @param {type} selecteur
 * @returns {undefined}
 */
function init_fancybox(selecteur)
{
    $(".image-gallery", selecteur || document).each(function ()
    {
        $(this).fancybox({
            helpers: {
                title: {
                    type: 'inside',
                    position: 'top'
                },
                overlay: {
                    locked: false
                }
            }
        }).click(function ()
        {
            return false;
        });
    });
}



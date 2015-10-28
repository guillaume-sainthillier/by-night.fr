$(function ()
{
    init_soirees();
});

function init_soirees(selector)
{
    init_unveil(selector);
    init_shorcut_date();
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
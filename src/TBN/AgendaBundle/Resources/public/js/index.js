$(function ()
{
    init_soirees();
});

function init_soirees(selector)
{
    init_shorcut_date();
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
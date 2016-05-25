$(function () {
    init_soirees();
});

function init_soirees() {
    init_shorcut_date();
}

/**
 * Initialise les boutons WE, cette semaine et ce mois
 * @returns {undefined}
 */
function init_shorcut_date() {
    $("select.shorcuts_date").unbind("change").change(function () {
        var selected = $(this).find("option:selected");
        $("#search_du").val(selected.data("date-debut") || "");
        $("#search_au").val(selected.data("date-fin") || "");
    });
}
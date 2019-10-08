import 'bootstrap-select/dist/css/bootstrap-select.css';

import 'bootstrap-select/dist/js/bootstrap-select.min.js';
import 'bootstrap-select/js/i18n/defaults-fr_FR.js';

$(function () {
    $("select.shorcuts_date").unbind("change").change(function () {
        var selected = $(this).find("option:selected");
        $("#search_du").val(selected.data("date-debut") || "");
        $("#search_au").val(selected.data("date-fin") || "");
    });
});

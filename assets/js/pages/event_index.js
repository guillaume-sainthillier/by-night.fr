import 'dropdown.js/jquery.dropdown.css';
import 'dropdown.js';

$(function () {
    $("select.shorcuts_date").unbind("change").change(function () {
        var selected = $(this).find("option:selected");
        $("#search_du").val(selected.data("date-debut") || "");
        $("#search_au").val(selected.data("date-fin") || "");
    });
});

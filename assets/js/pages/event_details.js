$(function () {
    var gMap = $("#googleMap").attr("data-toggled", "0");
    $("#loadMap").unbind("click").click(function (e) {
        if (!gMap.find("iframe").length) {
            $("<iframe>").attr({
                'class': 'component',
                width: gMap.width(),
                height: 450,
                frameborder: 0,
                src: $(this).data("map")
            }).css("width", "100%").appendTo(gMap);
        }

        if (gMap.attr("data-toggled") === "1") //Masquer
        {
            gMap.attr("data-toggled", "0").hide("normal");
        } else //Afficher
        {
            gMap.attr("data-toggled", "1").show("normal");
        }

        e.preventDefault();
        return false;
    });
});

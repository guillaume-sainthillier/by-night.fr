$(document).ready(function ()
{
    var gMap = $("#googleMap").slideToggle();
    $("#loadMap").click(function ()
    {        
        if(! gMap.find("iframe").length)
        {
            $("<iframe>").attr({'class' : 'component', width: 600, height: 450, frameborder: 0, src: $(this).data("map")}).appendTo(gMap);
        }
        $("#googleMap").slideToggle("normal");
    });
});
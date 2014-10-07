$(document).ready(function()
{
    init_on_off_social();
});


function init_on_off_social()
{
    $(".onoffswitch-label").unbind("click").click(function(event)
    {
        var $label = $(this);
        if($(this).prev(".onoffswitch-checkbox").prop('checked')) //Déconnexion
        {
            event.preventDefault();
            launch_social_disconnect($label);

        }else //Connexion
        {
            launch_social_connect($label);
        }

        return false;

    });
    
    $(".onoffswitch-checkbox").each(function()
    {
        $(this).attr({"checked" : $(this).hasClass("checked"), "disabled" : false});
    });
}


function launch_social_connect(label)
{
    popup($(label).data("href-connect"), label);
}



//Fonction appelée par la popup ouverte lors de la connexion à un réseau social
function on_connected_social(label, user)
{
    var bloc_config = $(label).closest(".bloc_config");
       
    bloc_config.find(".onoffswitch-checkbox").attr('checked',true).addClass("checked");    
    bloc_config.find(".username").text(user.username);
    /*
    bloc_config.find(".email").text(email);
    bloc_config.find(".username").text(username);
    */
    bloc_config.find(".when_on").slideDown("normal", function()
    {
        $(this).removeClass("hidden");
    });    
}


function launch_social_disconnect(label)
{
    var dialog = $("#dialog_details").modal("loading").modal("show");

    dialog.load(label.data("href-disconnect"),function()
    {
        init_checkbox(dialog.modal("getBody").find("input:checkbox"));
        dialog.find("form").unbind("submit").submit(function()
        {
            dialog.modal("loading");
            $.post($(this).attr("action")).done(function()
            {
                dialog.modal("hide");
                on_disconnected_social(label);
            });
            return false;
        });        
    });            
}

function on_disconnected_social(label)
{
    var bloc_config = $(label).closest(".bloc_config");
    
    bloc_config.find(".onoffswitch-checkbox").attr("checked",false).removeClass("checked");    
    bloc_config.find(".when_on").slideUp("normal",function()
    {
        $(this).addClass("hidden");        
    });    
}

function init_checkbox(ck)
{
    ck.unbind("click").click(function()
    {
        var div_alert = $(this).closest(".modal-body").find(".alert");
        if($(this).prop("checked"))
        {
            div_alert.removeClass("hidden");
        }else
        {
            div_alert.addClass("hidden");
        }
    });
}

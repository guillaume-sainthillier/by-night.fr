$(document).ready(function()
{
    init_on_off_social();
    
    //Actions par défaut
    $("body").on("wantConnect", function(event, label)
    {
        launch_social_connect($(label));
    });
    
    $("body").on("wantDisconnect", function(event, label)
    {
        launch_social_disconnect($(label));
    });
    
    $("body").on("hasDisconnected", function(event, label)
    {
        on_disconnected_social($(label));
    });
    
    //Appelée par la popup ouverte lors de la connexion à un réseau social
    $("body").on("hasConnected", function(event, ui)
    {
        var label = ui.target;
        var user = ui.user;
        
        var bloc_config = label.closest(".bloc_config");

        bloc_config.find(".onoffswitch-checkbox").prop('checked',true).addClass("checked");    
        bloc_config.find(".username").text(user.username);
        bloc_config.find(".when_on").slideDown("normal", function()
        {
            $(this).removeClass("hidden");
        });
    });
});


function init_on_off_social()
{
    $(".onoffswitch-label").unbind("click").click(function(event)
    {
        var label = $(this);
        var ck = $(this).prev(".onoffswitch-checkbox");
        
        if(ck.prop('checked') || ck.hasClass("checked")) //Déconnexion
        {
            event.preventDefault();
            $("body").trigger("wantDisconnect", label);
        }else //Connexion
        {
            $("body").trigger("wantConnect", label);
        }
        return false;
    });
}


function launch_social_connect(label)
{
    popup($(label).data("href-connect"), label);
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
                $("body").trigger("hasDisconnected", label);                
            });
            return false;
        });        
    });            
}

function on_disconnected_social(label)
{
    var bloc_config = label.closest(".bloc_config");
    
    bloc_config.find(".onoffswitch-checkbox").attr("checked", false).removeClass("checked");    
    bloc_config.find(".when_on").slideUp("normal", function()
    {
        $(this).addClass("hidden");        
    });    
}

/**
 * 
 * @param {type} ck
 * @returns {undefined}
 */
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

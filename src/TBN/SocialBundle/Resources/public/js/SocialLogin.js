define(function (require)
{
    var $ = require('jquery');

    var object = {
        init: function ()
        {
            $(function ()
            {
                object.initOnOff();
                
                //Actions par défaut
                $("body").on("wantConnect", function (event, label)
                {
                    object.launchSocialConnect($(label));
                });

                $("body").on("wantDisconnect", function (event, label)
                {
                    object.launchSocialDisconnect($(label));
                });

                $("body").on("hasDisconnected", function (event, label)
                {
                    object.onDisconnectedSocial($(label));
                });

                //Appelée par la popup ouverte lors de la connexion à un réseau social
                $("body").on("hasConnected", function (event, ui)
                {
                    var label = ui.target;
                    var user = ui.user;

                    var bloc_config = label.closest(".bloc_config");

                    bloc_config.find(".onoffswitch-checkbox").prop('checked', true).addClass("checked");
                    bloc_config.find(".username").text(user.username);
                    bloc_config.find(".when_on").slideDown("normal", function ()
                    {
                        $(this).removeClass("hidden");
                    });
                });
            });
        },
        initOnOff: function ()
        {
            $(".onoffswitch-label").unbind("click").click(function (event)
            {
                var label = $(this);
                var ck = $(this).prev(".onoffswitch-checkbox");

                if (ck.prop('checked') || ck.hasClass("checked")) //Déconnexion
                {
                    event.preventDefault();
                    $("body").trigger("wantDisconnect", label);
                } else //Connexion
                {
                    $("body").trigger("wantConnect", label);
                }
                return false;
            });
        },
        
        //Deps: ['app/App']
        launchSocialConnect: function (label)
        {
            require(['app/App'], function(App)
            {
                App.popup($(label).data("href-connect"), label);
            });
            
        },
        launchSocialDisconnect: function (label)
        {
            var dialog = $("#dialog_details").modal("loading").modal("show");

            dialog.load(label.data("href-disconnect"), function ()
            {
                object.initCheckbox(dialog.modal("getBody").find("input:checkbox"));
                dialog.find("form").unbind("submit").submit(function ()
                {
                    dialog.modal("loading");
                    $.post($(this).attr("action")).done(function ()
                    {
                        dialog.modal("hide");
                        $("body").trigger("hasDisconnected", label);
                    });
                    return false;
                });
            });
        },
        onDisconnectedSocial: function (label)
        {
            var bloc_config = label.closest(".bloc_config");

            bloc_config.find(".onoffswitch-checkbox").attr("checked", false).removeClass("checked");
            bloc_config.find(".when_on").slideUp("normal", function ()
            {
                $(this).addClass("hidden");
            });
        },
        /**
         * 
         * @param {type} ck
         * @returns {undefined}
         */
        initCheckbox: function (ck)
        {
            ck.unbind("click").click(function ()
            {
                var div_alert = $(this).closest(".modal-body").find(".alert");
                if ($(this).prop("checked"))
                {
                    div_alert.removeClass("hidden");
                } else
                {
                    div_alert.addClass("hidden");
                }
            });
        }
    };

    return object;
});

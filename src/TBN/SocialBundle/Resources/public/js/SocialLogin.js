var SocialLogin = {
    init: function () {
        $(function () {
            SocialLogin.initOnOff();

            //Actions par défaut
            $("body").on("wantConnect", function (event, ck) {
                SocialLogin.launchSocialConnect(ck);
            });

            $("body").on("wantDisconnect", function (event, ck) {
                SocialLogin.launchSocialDisconnect(ck);
            });

            $("body").on("hasDisconnected", function (event, ck) {
                SocialLogin.onDisconnectedSocial(ck);
            });

            //Appelée par la popup ouverte lors de la connexion à un réseau social
            $("body").on("hasConnected", function (event, ui) {
                var ck = ui.target;
                var user = ui.user;

                var bloc_config = $(ck).closest(".bloc_config");

                $(ck).prop('checked', true);
                bloc_config.find(".username").text(user.username);
                bloc_config.find(".when_on").show("normal", function () {
                    $(this).removeClass("hidden");
                });
            });
        });
    },
    initOnOff: function () {
        $(".bloc_config input:checkbox").each(function () {
            $(this).unbind("change").change(function () {
                var ck = $(this);
                $(ck).prop('checked', !$(ck).prop('checked'));
                if ($(ck).prop('checked')) //Déconnexion
                {
                    $("body").trigger("wantDisconnect", $(ck));
                } else //Connexion
                {
                    $("body").trigger("wantConnect", ck);
                }
            });
        });
    },
    //Deps: ['app/App']
    launchSocialConnect: function (ck) {
        App.popup($(ck).data("href-connect"), ck);
    },
    launchSocialDisconnect: function (ck) {
        var dialog = $("#dialog_details").modal("loading").modal("show");

        dialog.load($(ck).data("href-disconnect"), function () {
            SocialLogin.initModalCheckbox(dialog.modal("getBody").find("input:checkbox"));
            dialog.find("form").unbind("submit").submit(function () {
                dialog.modal("loading");
                $.post($(this).attr("action")).done(function () {
                    dialog.modal("hide");
                    $("body").trigger("hasDisconnected", $(ck));
                });
                return false;
            });
        });
    },
    onDisconnectedSocial: function (ck) {
        var bloc_config = $(ck).closest(".bloc_config");

        $(ck).prop("checked", false);
        bloc_config.find(".when_on").hide("normal", function () {
            $(this).addClass("hidden");
        });
    },
    /**
     *
     * @param {type} ck
     * @returns {undefined}
     */
    initModalCheckbox: function (ck) {
        $(ck).unbind("click").click(function () {
            var div_alert = $(this).closest(".modal-body").find(".alert");
            if ($(this).prop("checked")) {
                div_alert.removeClass("hidden");
            } else {
                div_alert.addClass("hidden");
            }
        });
    }
};

SocialLogin.init();
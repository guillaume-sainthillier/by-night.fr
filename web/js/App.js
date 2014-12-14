require(['jquery', 'require', 'bootstrap'], function ($, require)
{
    console.log("OK");
    var object = {
        init: function(selecteur)
        {
            $(function()
            {
                object.initComponents(selecteur);
                object.initSocialCounts();
                object.initHideMenuOnScroll();
                require(["scrollTo"], function()
                {
                    object.initScrollTo();
                });
            });
            
            return object;
        },
        
        initComponents: function(selecteur)
        {
            console.log("MATERIAL LOADING");
            require(['material', 'ripples'], function()
            {
                console.log("MATERIAL LOADED");
                $.material.init();
            });
            
            console.log("MODALS LOADING");
            require(['modals'], function()
            {
                console.log("MODALS LOADED");
                object.initConnexion(selecteur);
                object.initRegister(selecteur);
            });
            
            object.initTooltips(selecteur);
            object.initParticiper(selecteur);
            object.initAutofocus(selecteur);
            object.initMore(selecteur);
            object.initDatepicker(selecteur);
            object.initSelectpicker(selecteur);
        },
        
        //Deps: []
        initMore: function(container)
        {
            $(".more", container || document).click(function (e)
            {
                $(this).attr("disabled", true).prepend("<i class='fa fa-spin fa-spinner'></i> ");
                var container = $(this).parent();
                container.load($(this).attr("href"), function ()
                {
                    object.initMore(container);
                });

                e.preventDefault();
                return false;
            });
        },
        
        loadingButtons: function(selecteur)
        {
            $('.btn-submit', selecteur || document).button('loading');
        },

        resetButtons: function(selecteur)
        {
            $('.btn-submit', selecteur || document).button('reset');
        },

        /**
         * Deps: ['jquery-ui/i18n/datepicker-fr']
         * @param {jQuery|document} selecteur le selecteur pour le filtrage
         * @returns {void}
         */
        initDatepicker: function(selecteur)
        {
            var targets = $('.datepicker', selecteur || document);

            if (targets.length)
            {
                console.log("DATEPICKER LOADING");
                require(['datepicker-i18n'], function()
                {
                    targets.datepicker({
                        language: "fr",
                        autoclose: true,
                        todayHighlight: true
                    });
                    console.log("DATEPICKER LOADED");
                });
            }
        },
        
        /**
         * Deps: ['select-i18n']
         * @param {jQuery|document} selecteur le selecteur pour le filtrage
         * @returns {void}
         */
        initSelectpicker: function(selecteur)
        {
            var targets = $('select', selecteur || document);

            if (targets.length)
            {
                console.log("SELECT LOADING");
                require(['select-i18n'], function()
                {
                    console.log("SELECT LOADED");
                    targets.selectpicker();
                });                
            }
        },

        //Deps: []
        initSocialCounts: function()
        {
            if ($("#footer .social").length && window.get_social_count)
            {
                $.get(window.get_social_count).done(function (counts)
                {
                    $.each(counts, function (social, count)
                    {
                        $(".social." + social).find(".number").text(count);
                    });
                });
            }
        },

        //Deps: []
        initAutofocus: function(selecteur)
        {
            $("[autofocus]", selecteur || document).focus();
        },

        //Deps: []
        initHideMenuOnScroll: function()
        {
            $(window).scroll(function ()
            {
                $(".navbar-toggle").each(function ()
                {
                    var href = $(this).data("target");
                    var elem = $(href);
                    if (elem.length && elem.hasClass("in"))
                    {
                        elem.removeClass("in");
                    }
                });
            });
        },

        //Deps: ['bootstrap']
        initParticiper: function(selecteur)
        {
            var options = {
                "css_selecteur_participer": ".btn.participer",
                "css_selecteur_interesser": ".btn.interesser",
                "css_active_class": "active",
                "css_buttons": ".buttons",
                "css_hidden": "hidden",
                "css_selecteur_icon": ".check"
            };

            $(options.css_selecteur_participer + ", " + options.css_selecteur_interesser, selecteur || document).unbind("click").click(function ()
            {
                var btn = $(this);

                if (btn.hasClass(options.css_active_class))
                {
                    return false;
                }

                btn.data("loading-text", btn.text()).button("loading");
                $.post(btn.data("href")
                        ).done(function (msg)
                {
                    if (msg.success)
                    {
                        btn.button("reset");
                        if (msg.participer)//L'utilisateur participe
                        {
                            $(options.css_selecteur_interesser).removeClass(options.css_active_class).find(options.css_selecteur_icon).addClass(options.css_hidden);
                            $(options.css_selecteur_participer).addClass(options.css_active_class).find(options.css_selecteur_icon).removeClass(options.css_hidden);

                        }

                        if (msg.interet)//L'utilisateur est interessé
                        {
                            $(options.css_selecteur_participer).removeClass(options.css_active_class).find(options.css_selecteur_icon).addClass(options.css_hidden);
                            $(options.css_selecteur_interesser).addClass(options.css_active_class).find(options.css_selecteur_icon).removeClass(options.css_hidden);
                        }
                    }
                });
            }).each(function ()
            {
                if ($(this).hasClass(options.css_active_class))//L'utilisateur est interessé
                {
                    $(this).find(options.css_selecteur_icon).removeClass(options.css_hidden);
                } else
                {
                    $(this).find(options.css_selecteur_icon).addClass(options.css_hidden);
                }
            }).closest(options.css_buttons).removeClass(options.css_hidden);
        },

        //Deps: ['bootstrap']
        initTooltips: function(selecteur)
        {
            $(".tbn_tooltip", selecteur || document).tooltip();
        },

        //Deps: ['scrollTo']
        initScrollTo: function()
        {
            var settings = {
                text: 'En haut',
                min: 200,
                inDelay: 600,
                outDelay: 400,
                containerID: 'toTop',
                containerHoverID: 'toTopHover',
                scrollSpeed: 400,
                easingType: 'linear'
            };

            var toTopHidden = true;
            var toTop = $('#' + settings.containerID);

            toTop.click(function (e) {
                e.preventDefault();
                $.scrollTo(0, settings.scrollSpeed, {easing: settings.easingType});                             
            });

            $(window).scroll(function () {
                var sd = $(this).scrollTop();
                if (sd > settings.min && toTopHidden)
                {
                    toTop.fadeIn(settings.inDelay);
                    toTopHidden = false;
                }
                else if (sd <= settings.min && !toTopHidden)
                {
                    toTop.fadeOut(settings.outDelay);
                    toTopHidden = true;
                }
            });
        },

        //Deps: []
        popup: function(href, parent, width, height, left, top)
        {
            window.parent_elem = parent;

            width = width || 800,
                    height = height || 500,
                    left = left || ($(window).width() / 2) - (width / 2),
                    top = top || ($(window).height() / 2) - (height / 2);

            window.open(href, null, 'width=' + width + ',height=' + height + ',toolbar=0,menubar=0,location=0,status=0,scrollbars=1,resizable=1,left=' + left + ',top=' + top + ',toolbar=0');
        },

        //Deps: ['modals']
        initRegister: function(selecteur)
        {
            var object = this;
            $(".register", selecteur || document).unbind("click").click(function ()
            {
                var $dialog = $("#dialog_details");
                $dialog.modal("show").modal("loading").load($(this).attr("href"), function ()
                {
                    object.handleRegister($dialog);
                });

                return false;
            });
        },

        //Deps: ['modals', 'bootstrap']
        handleRegister: function($dialog)
        {
            var object = this;
            object.initComponents($dialog);
            $dialog.find("form").unbind("submit").submit(function ()
            {
                var href = $(this).attr("action");
                var datas = $(this).serialize();
                var submit_button = $("#_register");
                submit_button.button("loading");
                $.post(href, datas).done(function (data)
                {
                    submit_button.button("reset");

                    if (typeof data.success === "boolean" && data.success)
                    {
                        $dialog.modal("hide");
                        location.reload();
                    } else
                    {
                        $dialog.html(data);
                        object.handleRegister($dialog); //ne rien mettre après
                    }
                });
                return false;
            });
        },

        //Deps: ['modals']
        initConnexion: function(selecteur)
        {
            var object = this;
            $(".connexion", selecteur || document).unbind("click").click(function (e)
            {
                var $dialog = $("#dialog_details");
                $dialog.modal("show").modal("loading").load($(this).attr("href"), function ()
                {
                    object.handleLogin($dialog);
                });
                return false;
            });
        },

        //Deps: ['modals', 'bootstrap']
        handleLogin: function($dialog)
        {
            var object = this;
            object.initComponents($dialog);
            $dialog.find("form").unbind("submit").submit(function ()
            {
                var href = $(this).attr("action");
                var datas = $(this).serialize();
                var submit_button = $("#_submit");
                submit_button.button("loading");
                $.post(href, datas).done(function (data)
                {
                    submit_button.button("reset");
                    if (!data.success)
                    {
                        $dialog.modal("setLittleErreur", data.message);
                    } else
                    {
                        $dialog.modal("hide");
                        location.reload();
                    }
                });
                return false;
            });
        }
    };
    
    object.init();
    return object;
});
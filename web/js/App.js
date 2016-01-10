var App = {
    init: function (selecteur)
    {
        $(function ()
        {
            App.initComponents(selecteur);
            App.initPopups();
            App.initScrollTo();
        });
    },
    initPopups: function() {
        $('a.popup').click(function() {
            var width = 520,
                height = 350,
                leftPosition = (window.screen.width / 2) - ((width / 2) + 10),
                topPosition = (window.screen.height / 2) - ((height / 2) + 50),
                windowFeatures = "status=no,height=" + height + ",width=" + width + ",left=" + leftPosition + ",top=" + topPosition + ",screenX=" + leftPosition + ",screenY=" + topPosition + ",toolbar=0,status=0";
            
            window.open($(this).attr('href'), 'sharer', windowFeatures);
            return false;
        });
    },
    initComponents: function (selecteur)
    {
        $.material.init();
        App.initAutofocus(selecteur);
        App.initConnexion(selecteur);
        App.initRegister(selecteur);
        App.initTooltips(selecteur);
        App.initParticiper(selecteur);
        App.initMore(selecteur);
        App.initSelectpicker(selecteur);
        App.initDatepicker(selecteur)        
        App.initGallery(selecteur)        
    },
    initGallery: function(container) {
        $(".image-gallery", container || document).each(function ()
        {
            $(this).fancybox({
                helpers: {
                    title: {
                        type: 'inside',
                        position: 'top'
                    },
                    overlay: {
                        locked: false
                    }
                }
            }).click(function ()
            {
                return false;
            });
        });
    },
    //Deps: []
    initMore: function (container)
    {
        $(".more", container || document).click(function (e)
        {
            $(this).attr("disabled", true).prepend("<i class='fa fa-spin fa-spinner'></i> ");
            var container = $(this).parent();
            container.load($(this).attr("href"), function ()
            {
                App.initMore(container);
            });

            e.preventDefault();
            return false;
        });
    },
    loadingButtons: function (selecteur)
    {
        $('.btn-submit', selecteur || document).button('loading');
    },
    resetButtons: function (selecteur)
    {
        $('.btn-submit', selecteur || document).button('reset');
    },
    /**
     * Deps: ['jquery-ui/i18n/datepicker-fr']
     * @param {jQuery|document} selecteur le selecteur pour le filtrage
     * @returns {void}
     */
    initDatepicker: function (selecteur)
    {
        var targets = $('.widget_datepicker', selecteur || document);

        if (targets.length)
        {
            targets.datepicker({
                language: "fr",
                autoclose: true,
                todayHighlight: true
            });
        }
    },
    /**
     * Deps: ['select-i18n']
     * @param {jQuery|document} selecteur le selecteur pour le filtrage
     * @returns {void}
     */
    initSelectpicker: function (selecteur)
    {
        var targets = $('select[multiple]', selecteur || document);

        if (targets.length)
        {
            targets.selectpicker();
        }
        
        var targets = $('select:not([multiple])', selecteur || document);

        if (targets.length)
        {
            targets.dropdown();
        }
    },
    //Deps: []
    initAutofocus: function (selecteur)
    {
        $("[autofocus]", selecteur || document).focus();
    },
    //Deps: []
    initHideMenuOnScroll: function ()
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
    initParticiper: function (selecteur)
    {
        var options = {
            "css_selecteur_participer": ".btn.participer",
            "css_selecteur_interesser": ".btn.interesser",
            "css_active_class": "btn-primary",
            "css_unactive_class": "btn-default",
            "css_buttons": ".buttons",
            "css_hidden": "hidden",
            "css_selecteur_icon": ".check"
        };

        $(options.css_selecteur_participer + ", " + options.css_selecteur_interesser, selecteur || document).click(function ()
        {
            var btn = $(this);

            if (btn.hasClass(options.css_active_class) || btn.hasClass('connexion'))
            {
                return false;
            }

            btn.attr('disabled', true);
            $.post(btn.data("href")).done(function (msg)
            {
                btn.attr('disabled', !msg.success);
                if (msg.success)
                {
                    var active = msg.participer ? $(options.css_selecteur_participer) : $(options.css_selecteur_interesser);
                    var unActive = msg.participer ? $(options.css_selecteur_interesser) : $(options.css_selecteur_participer);
                    
                    unActive.removeClass(options.css_active_class).addClass(options.css_unactive_class).find(options.css_selecteur_icon).addClass(options.css_hidden);
                    active.removeClass(options.css_unactive_class).addClass(options.css_active_class).find(options.css_selecteur_icon).removeClass(options.css_hidden);
                    
                }
            });
        });
    },
    //Deps: ['bootstrap']
    initTooltips: function (selecteur)
    {
        $(".tbn_tooltip", selecteur || document).tooltip();
    },
    //Deps: ['scrollTo']
    initScrollTo: function ()
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
    popup: function (href, parent, width, height, left, top)
    {
        window.parent_elem = parent;

        width = width || 800,
                height = height || 500,
                left = left || ($(window).width() / 2) - (width / 2),
                top = top || ($(window).height() / 2) - (height / 2);

        window.open(href, null, 'width=' + width + ',height=' + height + ',toolbar=0,menubar=0,location=0,status=0,scrollbars=1,resizable=1,left=' + left + ',top=' + top + ',toolbar=0');
    },
    //Deps: ['modals']
    initRegister: function (selecteur)
    {
        $(".register", selecteur || document).unbind("click").click(function ()
        {
            var $dialog = $("#dialog_details");
            $dialog.modal("show").modal("loading").load($(this).attr("href"), function ()
            {
                App.handleRegister($dialog);
            });

            return false;
        });
    },
    //Deps: ['modals', 'bootstrap']
    handleRegister: function ($dialog)
    {
        App.initComponents($dialog);
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
                    App.handleRegister($dialog); //ne rien mettre après
                }
            });
            return false;
        });
    },
    //Deps: ['modals']
    initConnexion: function (selecteur)
    {
        $(".connexion", selecteur || document).unbind("click").click(function (e)
        {
            var $dialog = $("#dialog_details");
            $dialog.modal("show").modal("loading").load($(this).attr("href"), function ()
            {
                App.handleLogin($dialog);
            });
            return false;
        });
    },
    //Deps: ['modals', 'bootstrap']
    handleLogin: function ($dialog)
    {
        App.initComponents($dialog);
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

App.init();
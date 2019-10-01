import 'jquery-cookiebar/jquery.cookiebar.css';
import 'font-awesome/css/font-awesome.css';
import 'fancybox/dist/css/jquery.fancybox.css';
import '../scss/app.scss';

import './vendors';
import './overrides';
import './collections';

class App {
    init(selecteur) {
        const self = this;
        $(document).ready(function () {
            self.initComponents(selecteur);
            self.initPopups();
            self.initScrollTo();
            self.initHideMenuOnScroll();
            self.initHeaderSearch();
        });
    }

    initHeaderSearch() {
        var searchForm = $('.navbar .search-form');
        searchForm.find("input").focus(function () {
            searchForm.addClass('focus');
        }).blur(function () {
            searchForm.removeClass('focus');
        });
    }

    initPopups() {
        $('a.popup').click(function () {
            var width = 520,
                height = 350,
                leftPosition = (window.screen.width / 2) - ((width / 2) + 10),
                topPosition = (window.screen.height / 2) - ((height / 2) + 50),
                windowFeatures = "status=no,height=" + height + ",width=" + width + ",left=" + leftPosition + ",top=" + topPosition + ",screenX=" + leftPosition + ",screenY=" + topPosition + ",toolbar=0,status=0";

            window.open($(this).attr('href'), 'sharer', windowFeatures);
            return false;
        });
    }

    initLazyLoading(selecteur) {
        /**
         * Initialise le lazy loading des images
         * @param {type} selecteur
         * @returns {undefined}
         */
        $(".img, .loading", selecteur || document).unveil(200, function () {
            $(this).removeClass("loading").removeAttr("width").removeAttr("height");
        });
    }

    initComponents(selecteur) {
        const self = this;
        $(selecteur || 'body').bootstrapMaterialDesign();
        self.initLazyLoading(selecteur);
        self.initAutofocus(selecteur);
        self.initConnexion(selecteur);
        self.initRegister(selecteur);
        self.initTooltips(selecteur);
        self.initParticiper(selecteur);
        self.initMore(selecteur);
        self.initSelectpicker(selecteur);
        self.initDatepicker(selecteur);
        self.initGallery(selecteur);
    }

    initGallery(container) {
        $(".image-gallery", container || document).each(function () {
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
            }).click(function () {
                return false;
            });
        });
    }

    //Deps: []
    initMore(container) {
        const self = this;
        $(".more", container || document).click(function (e) {
            $(this).attr("disabled", true).prepend("<i class='fa fa-spin fa-spinner'></i> ");
            var container = $(this).parent();
            container.load($(this).attr("href"), function () {
                self.initMore(container);
                self.initComponents(container);
            });

            e.preventDefault();
            return false;
        });
    }

    loadingButtons(selecteur) {
        $('.btn-submit', selecteur || document).button('loading');
    }

    resetButtons(selecteur) {
        $('.btn-submit', selecteur || document).button('reset');
    }

    /**
     * Deps: ['jquery-ui/i18n/datepicker-fr']
     * @param {jQuery|document} selecteur le selecteur pour le filtrage
     * @returns {void}
     */
    initDatepicker(selecteur) {
        var targets = $('.widget_datepicker', selecteur || document);

        if (targets.length) {
            targets.datepicker({
                language: "fr",
                autoclose: true,
                todayHighlight: true
            });
        }
    }

    /**
     * Deps: ['select-i18n']
     * @param {jQuery|document} selecteur le selecteur pour le filtrage
     * @returns {void}
     */
    initSelectpicker(selecteur) {
        var targets = $('select[multiple]', selecteur || document);
        if (targets.length) {
            targets.selectpicker();
        }

        var targets = $('select:not([multiple])', selecteur || document);
        if (targets.length) {
            targets.dropdown();
        }
    }

    //Deps: []
    initAutofocus(selecteur) {
        $("[autofocus]", selecteur || document).focus();
    }

    //Deps: []
    initHideMenuOnScroll() {
        var toggle = $(".navbar-toggle");
        $(window).scrolled(200, function () {
            toggle.each(function () {
                var href = $(this).data("target");
                var elem = $(href);
                if (elem.length && elem.hasClass("in")) {
                    elem.removeClass("in");
                }
            });
        });
    }

    //Deps: ['bootstrap']
    initParticiper(selecteur) {
        var options = {
            "css_selecteur_participer": ".btn.participer",
            "css_selecteur_interesser": ".btn.interesser",
            "css_active_class": "btn-primary",
            "css_unactive_class": "btn-default",
            "css_buttons": ".buttons",
            "css_hidden": "hidden",
            "css_selecteur_icon": ".check"
        };

        $(options.css_selecteur_participer + ", " + options.css_selecteur_interesser, selecteur || document).click(function () {
            var btn = $(this);

            if (btn.hasClass(options.css_active_class) || btn.hasClass('connexion')) {
                return false;
            }

            btn.attr('disabled', true);
            $.post(btn.data("href")).done(function (msg) {
                btn.attr('disabled', !msg.success);
                if (msg.success) {
                    var active = msg.participer ? $(options.css_selecteur_participer) : $(options.css_selecteur_interesser);
                    var unActive = msg.participer ? $(options.css_selecteur_interesser) : $(options.css_selecteur_participer);

                    unActive.removeClass(options.css_active_class).addClass(options.css_unactive_class).find(options.css_selecteur_icon).addClass(options.css_hidden);
                    active.removeClass(options.css_unactive_class).addClass(options.css_active_class).find(options.css_selecteur_icon).removeClass(options.css_hidden);

                }
            });
        });
    }

    //Deps: ['bootstrap']
    initTooltips(selecteur) {
        $(".app_tooltip", selecteur || document).tooltip();
    }

    //Deps: ['scrollTo']
    initScrollTo() {
        var settings = {
            text: 'En haut',
            min: 200,
            inDelay: 600,
            outDelay: 400,
            containerID: 'toTop',
            scrollSpeed: 400,
            easingType: 'linear'
        };

        var toTopHidden = true;
        var toTop = $('#' + settings.containerID);

        toTop.click(function (e) {
            e.preventDefault();
            $.scrollTo(0, settings.scrollSpeed, {easing: settings.easingType});
        });

        $(window).scrolled(200, function () {
            var sd = $(this).scrollTop();
            if (sd > settings.min && toTopHidden) {
                toTop.fadeIn(settings.inDelay);
                toTopHidden = false;
            } else if (sd <= settings.min && !toTopHidden) {
                toTop.fadeOut(settings.outDelay);
                toTopHidden = true;
            }
        });
    }

    //Deps: []
    popup(href, parent, width, height, left, top) {
        window.parent_elem = parent;

        width = width || 800,
            height = height || 500,
            left = left || ($(window).width() / 2) - (width / 2),
            top = top || ($(window).height() / 2) - (height / 2);

        window.open(href, null, 'width=' + width + ',height=' + height + ',toolbar=0,menubar=0,location=0,status=0,scrollbars=1,resizable=1,left=' + left + ',top=' + top + ',toolbar=0');
    }

    //Deps: ['modals']
    initRegister(selecteur) {
        const self = this;
        $(".register", selecteur || document).unbind("click").click(function () {
            var $dialog = $("#dialog_details");
            $dialog.modal("show").modal("loading").load($(this).attr("href"), function () {
                self.handleRegister($dialog);
            });

            return false;
        });
    }

    //Deps: ['modals', 'bootstrap']
    handleRegister($dialog) {
        const self = this;
        self.initComponents($dialog);
        $dialog.find("form").unbind("submit").submit(function () {
            var href = $(this).attr("action");
            var datas = $(this).serialize();
            var submit_button = $("#_register");
            submit_button.button("loading");
            $.post(href, datas).done(function (data) {
                submit_button.button("reset");

                if (typeof data.success === "boolean" && data.success) {
                    $dialog.modal("hide");
                    location.reload();
                } else {
                    $dialog.html(data);
                    self.handleRegister($dialog); //ne rien mettre après
                }
            });
            return false;
        });
    }

    //Deps: ['modals']
    initConnexion(selecteur) {
        const self = this;
        $(".connexion", selecteur || document).unbind("click").click(function (e) {
            var $dialog = $("#dialog_details");
            $dialog.modal("show").modal("loading").load($(this).attr("href"), function () {
                self.handleLogin($dialog);
            });
            return false;
        });
    }

    //Deps: ['modals', 'bootstrap']
    handleLogin($dialog) {
        const self = this;
        self.initComponents($dialog);
        $dialog.find("form").unbind("submit").submit(function () {
            var href = $(this).attr("action");
            var datas = $(this).serialize();
            var submit_button = $("#_submit");
            submit_button.button("loading");
            $.post(href, datas).done(function (data) {
                submit_button.button("reset");
                if (!data.success) {
                    $dialog.modal("setLittleErreur", data.message);
                } else {
                    $dialog.modal("hide");
                    location.reload();
                }
            });
            return false;
        });
    }
}

global.App = window.App = new App();
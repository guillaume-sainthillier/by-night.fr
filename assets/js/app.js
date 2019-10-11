import 'font-awesome/css/font-awesome.css';
import '../scss/app.scss';
import './vendors';
import './overrides';
import './collections';
import LazyLoad from "vanilla-lazyload";

class App {
    init(selecteur) {
        const self = this;
        $(document).ready(function () {
            self.initComponents(selecteur);
            self.initPopups();
            self.initScrollTo();
            self.initMenuOnScrollListener();
            self.initHeaderSearch();
            self.initBreadcrumb();
        });
    }

    initBreadcrumb() {
        var bread = $('#bread .breadcrumb');
        var btnCollapse = $('#bread .btn');

        btnCollapse.click(function () {
            bread.toggleClass('collapsed');
            $(this).find('.fa').toggleClass('fa-chevron-down').toggleClass('fa-chevron-right');
        });
    }

    initHeaderSearch() {
        var searchForm = $('.navbar .search-form');
        var searchBackdrop = $('#search-menu-backdrop');
        searchForm.find("input").focus(function () {
            searchForm.addClass('focus');
            searchBackdrop.addClass('open');
        }).blur(function () {
            searchForm.removeClass('focus');
            searchBackdrop.removeClass('open');
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
        var images = $("img.loading", selecteur || document);
        var scrollAreas = $(".scroll-area", selecteur || document);
        if (images.length === 0 && scrollAreas.length === 0) {
            return;
        }

        var callback_enter = function (element) {
            var width = $(element).width();
            var ratio = $(element).attr('width') / $(element).attr('height');

            var placeholder = $('<div>')
                .addClass('placeholder')
                .width(width)
                .height(width / ratio);

            placeholder.append($('<div>').addClass('loading-background'));
            $(element).addClass('d-none');
            $(element).after($(placeholder));
        };

        var callback_loaded = function (element) {
            $(element).siblings('.placeholder').remove();
            $(element).removeClass('d-none');
        };

        var callback_error = function (element) {
            $(element).siblings('.placeholder').find('.loading-background').remove();
        };

        if (images.length > 0) {
            new LazyLoad({
                elements_selector: "img.loading",
                threshold: 200,
                container: (selecteur && selecteur[0]) || document,
                /*callback_enter: callback_enter,
                callback_loaded: callback_loaded,
                callback_error: callback_error,*/
            });
        }

        if (scrollAreas.length > 0) {
            new LazyLoad({
                elements_selector: ".scroll-area",
                container: (selecteur && selecteur[0]) || document,

                callback_enter: function (el) {
                    new LazyLoad({
                        elements_selector: "img.loading",
                        threshold: 200,
                        container: el,
                        /*callback_enter: callback_enter,
                        callback_loaded: callback_loaded,
                        callback_error: callback_error,*/
                    });
                }
            });
        }
    }

    initComponents(selecteur) {
        const self = this;
        $(selecteur || 'body').data('bmd.bootstrapMaterialDesign', null).bootstrapMaterialDesign();
        self.initLazyLoading(selecteur);
        self.initAutofocus(selecteur);
        self.initConnexion(selecteur);
        self.initRegister(selecteur);
        self.initTooltips(selecteur);
        self.initLike(selecteur);
        self.initMore(selecteur);
        self.initSelectpicker(selecteur);
        self.initDatepicker(selecteur);
        self.initGallery(selecteur);
        self.initShortcutDates(selecteur);
    }

    initShortcutDates(container) {
        $("select.shorcuts_date", container || document).change(function () {
            var selected = $(this).find("option:selected");
            $("#du").val(selected.data("date-debut") || "");
            $("#au").val(selected.data("date-fin") || "");
        }).trigger('change');
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
            $(this).attr("disabled", true).prepend('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> ');
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
        $('.btn-submit', selecteur || document)
            .attr('disabled', true)
            .prepend('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> ');
    }

    resetButtons(selecteur) {
        $('.btn-submit', selecteur || document)
            .attr('disabled', false)
            .find('.spinner-border')
            .remove();
    }

    /**
     * Deps: ['jquery-ui/i18n/datepicker-fr']
     * @param {jQuery|document} selecteur le selecteur pour le filtrage
     * @returns {void}
     */
    initDatepicker(selecteur) {
        var targets = $('input.widget_datepicker', selecteur || document);

        if (!targets.length) {
            return;
        }

        targets.datepicker({
            language: "fr",
            autoclose: true,
            todayHighlight: true
        });
    }

    /**
     * Deps: ['select-i18n']
     * @param {jQuery|document} selecteur le selecteur pour le filtrage
     * @returns {void}
     */
    initSelectpicker(selecteur) {
        $('select', selecteur || document).each(function () {
            $(this).selectpicker({
                'style': $(this).data('style') || 'btn-primary'
            });
        });
    }

    //Deps: []
    initAutofocus(selecteur) {
        $("[autofocus]", selecteur || document).focus();
    }

    //Deps: []
    initMenuOnScrollListener() {
        var navbar = $('.navbar');
        var toggler = navbar.find(".navbar-toggler");
        var href = $(toggler).data("target");
        var elem = $(href);

        $(window).scrolled(200, function () {
            if (!toggler.hasClass("collapsed")) {
                $(elem).collapse('hide');
            }
        });

        $(window).scroll(function () {
            if ($(window).scrollTop() > 0) {
                $(navbar).addClass('navbar-shadow');
            } else {
                $(navbar).removeClass('navbar-shadow');
            }
        })
    }

    //Deps: ['bootstrap']
    initLike(selecteur) {
        var options = {
            "css_selecteur_like": ".btn-like-event",
            "css_active_class": "btn-primary"
        };

        $(options.css_selecteur_like, selecteur || document).click(function () {
            var btn = $(this);

            if (btn.hasClass('connexion')) {
                return false;
            }

            btn.attr('disabled', true);
            $.post(btn.data("href"), {'like': !btn.hasClass(options.css_active_class)}).done(function (msg) {
                btn.attr('disabled', !msg.success);
                if (msg.success) {
                    btn.toggleClass(options.css_active_class, msg.like);
                }
            });
        });
    }

    //Deps: ['bootstrap']
    initTooltips(selecteur) {
        $('[data-toggle="tooltip"]', selecteur || document).tooltip();
    }

    //Deps: ['scrollTo']
    initScrollTo() {
        var settings = {
            min: 200,
            inDelay: 300,
            outDelay: 200,
            containerID: 'toTop',
            scrollSpeed: 400,
            easingType: 'linear'
        };

        var toTopHidden = true;
        var toTop = $('#' + settings.containerID);

        if (!toTop.length) {
            return;
        }

        toTop.click(function (e) {
            e.preventDefault();
            $("html, body").animate({'scrollTop': 0}, settings.scrollSpeed, settings.easingType);
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
import 'bootstrap-select/dist/css/bootstrap-select.css';
import 'daterangepicker/daterangepicker.css';

import 'bootstrap-select/dist/js/bootstrap-select.min.js';
import 'bootstrap-select/js/i18n/defaults-fr_FR.js';
import 'moment/locale/fr';
import 'daterangepicker';

import Widgets from '../components/Widgets';

$(function () {
    init_custom_tab();
    init_criteres();
    load_infinite_scroll();
    init_soirees();

    var countLoads = 0;
    var isLoading = false;
    var widgets = new Widgets();
    widgets.init();

    function init_soirees() {
        init_pagination();
    }

    function load_infinite_scroll() {
        var marginScroll = 250;
        var countStep = 2;

        $(window).scrolled(200, function () {
            if (countLoads < countStep || isLoading) {
                return;
            }

            var paginate = $('#paginate');
            if (paginate.length > 0 && $(window).scrollTop() + $(window).height() > paginate.offset().top - marginScroll) {
                isLoading = true;
                paginate.trigger('click');
            }
        });
    }

    function init_custom_tab() {
        var tabs = $('#custom-tab');
        tabs.find('a.nav-link').click(function () {
            var oldActive = $(this).closest('.nav').find('a.nav-link.active');
            if (oldActive[0] !== this) {
                desactivate(oldActive);
                activate(this);
            }

            return false;
        });

        function activate(tab) {
            var target = $(tab).attr('href');
            $(target).addClass('active');
            $(tab).addClass('active');
            $("html, body").animate({'scrollTop': 0}, 'fast');
        }

        function desactivate(tab) {
            var target = $(tab).attr('href');
            $(target).removeClass('active');
            $(tab).removeClass('active');
        }

        var lastScrollTop = 0;
        var toTop = $('#toTop');
        var bottomNavigation = $('#bottom-navigation');
        $(window).scroll(function () {
            var st = $(this).scrollTop();
            if (st > lastScrollTop) {
                toTop.removeClass('hidden');
                bottomNavigation.removeClass('visible');
            } else {
                toTop.addClass('hidden');
                bottomNavigation.addClass('visible');
            }
            lastScrollTop = st;
        });
    }

    function init_pagination() {
        $('#paginate').click(function (e) {
            isLoading = true;
            countLoads++;
            $(this).attr('disabled', true).prepend('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> ');

            var self = $(this);
            var container = self.parent();
            $.get($(self).attr('href')).done(function (html) {
                isLoading = false;
                self.replaceWith(html);
                App.initComponents(container);
                init_soirees(container);
            });

            e.preventDefault();
            return false;
        });
    }

    /**
     *
     */
    function init_criteres() {
        var options = {
            "css_hidden": "cache",
            "css_initial_hidden": 'hidden',
            "css_icon_class_open": 'fa-chevron-down',
            "css_icon_class_close": 'fa-chevron-up',
            "selector_btn_criteres": ".btn_criteres",
            "selector_icon": ".fa",
            "selector_block_criteres": ".criteres",
            "selector_main_block": ".block_criteres",
            "duration": 0
        };

        //Bon bloc indigeste :)
        var block = $(options.selector_btn_criteres).click(function () {
            if (block.hasClass(options.css_hidden)) {
                $(this).find(options.selector_icon).removeClass(options.css_icon_class_open).addClass(options.css_icon_class_close);
                block.show(options.duration, function () {
                    $(this).removeClass(options.css_hidden);
                });
            } else {
                $(this).find(options.selector_icon).removeClass(options.css_icon_class_close).addClass(options.css_icon_class_open);
                block.hide(options.duration, function () {
                    $(this).addClass(options.css_hidden);
                });
            }
        })
            .closest(options.selector_main_block)
            .find(options.selector_block_criteres);

        //Pas de besoins d'ouvrir la recherche avancée
        if (block.hasClass(options.css_hidden)) {
            block.hide().removeClass(options.css_initial_hidden);
        }
    }
});

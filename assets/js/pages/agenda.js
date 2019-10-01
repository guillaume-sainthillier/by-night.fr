import 'dropdown.js/jquery.dropdown.css';
import 'bootstrap-datepicker/dist/css/bootstrap-datepicker3.css';
import 'bootstrap-select/dist/css/bootstrap-select.min.css';

import 'bootstrap-datepicker/dist/js/bootstrap-datepicker.js';
import 'bootstrap-datepicker/dist/locales/bootstrap-datepicker.fr.min.js';
import 'bootstrap-select/dist/js/bootstrap-select.min.js';
import 'bootstrap-select/js/i18n/defaults-fr_FR.js';
import 'dropdown.js';

import Widgets from '../components/Widgets';

$(function () {
    init_criteres();
    init_shorcut_date();
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

        $(window).scroll(function () {
            if (countLoads < countStep || isLoading) {
                return;
            }

            var paginate = $('#paginate');
            if ($(window).scrollTop() + $(window).height() > paginate.offset().top - marginScroll) {
                isLoading = true;
                paginate.trigger('click');
            }
        });
    }

    function init_pagination() {
        $('#paginate').click(function (e) {
            isLoading = true;
            countLoads++;
            $(this).attr('disabled', true).html($('<i>').addClass('fa fa-spin fa-spinner'));

            var self = $(this);
            var container = self.parent();
            var page = self.data('next');
            var form = $('form[name="search"]');
            var pageInput = $('#search_page');

            pageInput.val(page);
            $.post(form.attr('action'), form.serialize()).done(function (html) {
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

    /**
     * Initialise les boutons WE, cette semaine et ce mois
     * @returns {undefined}
     */
    function init_shorcut_date() {
        $("select.shorcuts_date").unbind("change").change(function () {
            var selected = $(this).find("option:selected");
            $("#search_du").val(selected.data("date-debut") || "");
            $("#search_au").val(selected.data("date-fin") || "");
        });
    }
});

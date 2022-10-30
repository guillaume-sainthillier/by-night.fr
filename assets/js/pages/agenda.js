import debounce from 'lodash/debounce';

import initDates from '../lazy-listeners/dates';
import initImagePreview from '../lazy-listeners/image-previews';
import initSelects from '../lazy-listeners/selects';

import Widgets from '../components/Widgets';

$(document).ready(function () {
    initDates();
    initImagePreview();
    initSelects();
    initCustomTab();
    initCriterions();
    loadInfiniteScroll();
    initPagination();

    let countLoads = 0;
    let isLoading = false;
    const widgets = new Widgets();
    widgets.init();

    function loadInfiniteScroll() {
        const marginScroll = 250;
        const countStep = 2;
        const paginate = $('#paginate');

        $(window).scroll(
            debounce(
                function () {
                    if (countLoads < countStep || isLoading) {
                        return;
                    }

                    if (
                        paginate.length > 0 &&
                        $(window).scrollTop() + $(window).height() > paginate.offset().top - marginScroll
                    ) {
                        isLoading = true;
                        paginate.trigger('click');
                    }
                },
                200,
                { leading: true }
            )
        );
    }

    function initCustomTab() {
        const tabs = $('#custom-tab');
        tabs.find('a.nav-link').click(function () {
            const oldActive = $(this).closest('.nav').find('a.nav-link.active');
            if (oldActive[0] !== this) {
                desactivate(oldActive);
                activate(this);
            }

            return false;
        });

        function activate(tab) {
            const target = $(tab).attr('href');
            $(target).addClass('active');
            $(tab).addClass('active');
            $('html, body').animate({ scrollTop: 0 }, 'fast');
        }

        function desactivate(tab) {
            const target = $(tab).attr('href');
            $(target).removeClass('active');
            $(tab).removeClass('active');
        }

        let lastScrollTop = 0;
        const toTop = $('#toTop');
        const bottomNavigation = $('#bottom-navigation');
        $(window).scroll(function () {
            const st = $(this).scrollTop();
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

    function initPagination() {
        $('#paginate').click(function (e) {
            e.preventDefault();

            isLoading = true;
            countLoads++;
            $(this)
                .attr('disabled', true)
                .prepend('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> ');

            const btn = $(this);
            const container = btn.parent().prev();
            $.get($(btn).attr('href'), function (html) {
                isLoading = true;
                const currentContainer = $('<div>').html(html);
                btn.parent().remove();
                currentContainer.insertAfter(container);
                App.dispatchPageLoadedEvent(currentContainer[0]);
                initPagination(currentContainer);
            });
        });
    }

    /**
     *
     */
    function initCriterions() {
        const options = {
            css_hidden: 'cache',
            css_initial_hidden: 'hidden',
            css_icon_class_open: 'fa-chevron-down',
            css_icon_class_close: 'fa-chevron-up',
            selector_btn_criteres: '.btn_criteres',
            selector_icon: '.fa',
            selector_block_criteres: '.criteres',
            selector_main_block: '.block_criteres',
            duration: 0,
        };

        // Bon bloc indigeste :)
        const block = $(options.selector_btn_criteres)
            .click(function () {
                if (block.hasClass(options.css_hidden)) {
                    $(this)
                        .find(options.selector_icon)
                        .removeClass(options.css_icon_class_open)
                        .addClass(options.css_icon_class_close);
                    block.show(options.duration, function () {
                        $(this).removeClass(options.css_hidden);
                    });
                } else {
                    $(this)
                        .find(options.selector_icon)
                        .removeClass(options.css_icon_class_close)
                        .addClass(options.css_icon_class_open);
                    block.hide(options.duration, function () {
                        $(this).addClass(options.css_hidden);
                    });
                }
            })
            .closest(options.selector_main_block)
            .find(options.selector_block_criteres);

        // Pas de besoins d'ouvrir la recherche avancée
        if (block.hasClass(options.css_hidden)) {
            block.hide().removeClass(options.css_initial_hidden);
        }
    }
});

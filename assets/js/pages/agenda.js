import $ from 'jquery'
import debounce from 'lodash/debounce'

import { create as createDatepicker } from '@/js/services/ui/DatepickerService'
import { create as createFancybox } from '@/js/services/ui/FancyboxService'
import { create as createSelect } from '@/js/services/ui/SelectService'

import Widgets from '@/js/components/Widgets'
import ChevronUpIcon from '@/js/icons/lucide/ChevronUp'
import ChevronDownIcon from '@/js/icons/lucide/ChevronDown'
import { iconHtml } from '@/js/components/icons'

function initDatepickers(container = document) {
    container.querySelectorAll('input.shorcuts_date').forEach((el) => {
        createDatepicker({
            element: el,
            fromInput: document.getElementById(el.dataset.from),
            toInput: document.getElementById(el.dataset.to),
            singleDate: el.dataset.singleDate === 'true',
            ranges: el.dataset.ranges ? JSON.parse(el.dataset.ranges) : {},
        })
    })
}

function initFancyboxes(container = document) {
    container.querySelectorAll('.image-gallery').forEach((el) => {
        createFancybox({ element: el })
    })
}

function initSelects(container = document) {
    container.querySelectorAll('select.form-select:not(.hidden):not(.tomselected)').forEach((el) => {
        createSelect({ element: el })
    })
}

$(document).ready(function () {
    initDatepickers()
    initFancyboxes()
    initSelects()
    initCustomTab()
    initCriterions()
    loadInfiniteScroll()
    initPagination()

    let countLoads = 0
    let isLoading = false
    const widgets = new Widgets()
    widgets.init()

    function loadInfiniteScroll() {
        const marginScroll = 250
        const countStep = 2
        const paginate = $('#paginate')

        $(window).scroll(
            debounce(
                function () {
                    if (countLoads < countStep || isLoading) {
                        return
                    }

                    if (
                        paginate.length > 0 &&
                        $(window).scrollTop() + $(window).height() > paginate.offset().top - marginScroll
                    ) {
                        isLoading = true
                        paginate.trigger('click')
                    }
                },
                200,
                { leading: true }
            )
        )
    }

    function initCustomTab() {
        const tabs = $('#custom-tab')
        tabs.find('a.nav-link').click(function () {
            const oldActive = $(this).closest('.nav').find('a.nav-link.active')
            if (oldActive[0] !== this) {
                desactivate(oldActive)
                activate(this)
            }

            return false
        })

        function activate(tab) {
            const target = $(tab).attr('href')
            $(target).addClass('active')
            $(tab).addClass('active')
            $('html, body').animate({ scrollTop: 0 }, 'fast')
        }

        function desactivate(tab) {
            const target = $(tab).attr('href')
            $(target).removeClass('active')
            $(tab).removeClass('active')
        }

        let lastScrollTop = 0
        const toTop = $('#toTop')
        const bottomNavigation = $('#bottom-navigation')
        $(window).scroll(function () {
            const st = $(this).scrollTop()
            if (st > lastScrollTop) {
                toTop.removeClass('hidden')
                bottomNavigation.removeClass('visible')
            } else {
                toTop.addClass('hidden')
                bottomNavigation.addClass('visible')
            }
            lastScrollTop = st
        })
    }

    function initPagination() {
        $('#paginate').click(function (e) {
            e.preventDefault()

            isLoading = true
            countLoads++
            $(this)
                .attr('disabled', true)
                .prepend('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> ')

            const btn = $(this)
            const container = btn.parent().prev()
            $.get($(btn).attr('href'), function (html) {
                isLoading = true
                const currentContainer = $('<div>').html(html)
                btn.parent().remove()
                currentContainer.insertAfter(container)
                window.App.dispatchPageLoadedEvent(currentContainer[0])
                initPagination(currentContainer)
            })
        })
    }

    // Initialize the filters toggle behavior
    function initCriterions() {
        const options = {
            css_hidden: 'cache',
            css_initial_hidden: 'hidden',
            icon_open: iconHtml(ChevronDownIcon),
            icon_close: iconHtml(ChevronUpIcon),
            selector_filter_btn: '.filter-toggle-btn',
            selector_icon: '.icon-toggle',
            selector_filters: '.filters',
            selector_filter_block: '.filter-block',
            duration: 0,
        }

        // Toggle block logic
        const block = $(options.selector_filter_btn)
            .click(function () {
                if (block.hasClass(options.css_hidden)) {
                    $(this).find(options.selector_icon).html(options.icon_close)
                    block.show(options.duration, function () {
                        $(this).removeClass(options.css_hidden)
                    })
                } else {
                    $(this).find(options.selector_icon).html(options.icon_open)
                    block.hide(options.duration, function () {
                        $(this).addClass(options.css_hidden)
                    })
                }
            })
            .closest(options.selector_filter_block)
            .find(options.selector_filters)

        // No need to open advanced search by default
        if (block.hasClass(options.css_hidden)) {
            block.hide().removeClass(options.css_initial_hidden)
        }
    }
})

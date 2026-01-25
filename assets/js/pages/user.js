import $ from 'jquery'
import Raphael from 'raphael/raphael'
import 'morris.js/morris.css'
import 'morris.js/morris'
import Loader2Icon from '@/js/icons/lucide/Loader2'
import {iconHtml} from "@/js/components/icons"

global.Raphael = Raphael

$(document).ready(function () {
    init()

    function init() {
        initCharts()
        initLoadMoreEvents()
    }

    function initLoadMoreEvents() {
        $('.user-events-container').each(function() {
            const container = $(this)
            bindLoadMore(container)
        })
    }

    function bindLoadMore(container) {
        container.find('.load-more').off('click').on('click', function(e) {
            e.preventDefault()

            const loadMore = $(this)
            const btn = loadMore.find('.btn')

            // Add spinner to button
            const originalText = btn.html()
            btn.html(iconHtml(Loader2Icon, 'icon-spin') + ' ' + originalText)
            btn.prop('disabled', true)

            $.get(loadMore.data('url'), function(html) {
                // Remove the load-more button
                loadMore.remove()

                // Append new content
                const $newContent = $(html)
                container.append($newContent)

                // Re-bind load-more on the new content
                bindLoadMore(container)

                // Re-initialize any page listeners on new event cards
                window.App.dispatchPageLoadedEvent(container[0])
            }).fail(function() {
                // Restore button on error
                btn.html(originalText)
                btn.prop('disabled', false)
            })
        })
    }

    function initCharts() {
        initLieux()
        initActivite()
    }

    function initActivite() {
        chartActivite('annee', ['#67C2EF'])
        $('#chartMois').on('shown.bs.tab', function () {
            if (!$(this).hasClass('loaded')) {
                $(this).addClass('loaded')
                chartActivite('mois', ['#BDEA74'])
            }
        })

        $('#chartSemaine').on('shown.bs.tab', function () {
            if (!$(this).hasClass('loaded')) {
                $(this).addClass('loaded')
                chartActivite('semaine', ['#fabb3d'])
            }
        })
    }

    function initLieux() {
        const data = []

        $.each(window.datas, function (i, datum) {
            data.push({ label: datum.name || '', value: datum.eventsCount })
        })

        window.Morris.Donut({
            element: 'hero-donut',
            data,
            colors: ['#36A9E1', '#bdea74', '#67c2ef', '#fabb3d', '#ff5454'],
            formatter(y) {
                return y
            },
            resize: true,
        })
    }

    function prepareActivite(datas) {
        return datas.data.map(function (events, index) {
            return { period: datas.categories[index], events, full_period: datas.full_categories[index] }
        })
    }

    function chartActivite(type, colors) {
        const element = `chart-${type}`
        const chart = $(`#${element}`)
        $.get(chart.data('url')).done(function (datas) {
            chart.children().remove()
            window.Morris.Area({
                element,
                lineColors: colors,
                data: prepareActivite(datas),
                xkey: 'period',
                ykeys: ['events'],
                labels: ['Événements'],
                pointSize: 2,
                hideHover: 'auto',
                parseTime: false,
                resize: true,
                hoverCallback(index, options, content, row) {
                    const customContent = $(`<div>${content}</div>`)
                    $(customContent).find('.morris-hover-row-label').html(row.full_period)
                    return $(customContent).html()
                },
                gridTextFamily: 'Roboto',
                gridTextSize: '14',
            })
        })
    }
})

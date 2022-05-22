import Raphael from 'raphael/raphael';
import 'morris.js/morris.css';
import 'morris.js/morris';

global.Raphael = Raphael;

$(document).ready(function () {
    init();

    function init() {
        initCharts();
    }

    function initCharts() {
        $('.chart').css({ height: '350px', width: '100%' });

        initLieux();
        initActivite();
    }

    function initActivite() {
        chartActivite('annee', ['#67C2EF']);
        $('#chartMois').on('shown.bs.tab', function () {
            if (!$(this).hasClass('loaded')) {
                $(this).addClass('loaded');
                chartActivite('mois', ['#BDEA74']);
            }
        });

        $('#chartSemaine').on('shown.bs.tab', function () {
            if (!$(this).hasClass('loaded')) {
                $(this).addClass('loaded');
                chartActivite('semaine', ['#fabb3d']);
            }
        });
    }

    function initLieux() {
        var morris_data = [];

        $.each(window.datas, function (i, etablissement) {
            morris_data.push({ label: etablissement.nom || '', value: etablissement.nbEtablissements });
        });

        Morris.Donut({
            element: 'hero-donut',
            data: morris_data,
            colors: ['#36A9E1', '#bdea74', '#67c2ef', '#fabb3d', '#ff5454'],
            formatter(y) {
                return y;
            },
            resize: true,
        });
    }

    function prepare(dataArray) {
        return dataArray.map(function (item, index) {
            return { y: item, myIndex: index };
        });
    }

    function prepareActivite(datas) {
        return datas.data.map(function (events, index) {
            return { period: datas.categories[index], events: events, full_period: datas.full_categories[index] };
        });
    }

    function chartActivite(type, colors) {
        var element = 'chart-' + type;
        var chart = $('#' + element);
        $.get(chart.data('url')).done(function (datas) {
            chart.children().remove();
            Morris.Area({
                element: element,
                lineColors: colors,
                data: prepareActivite(datas),
                xkey: 'period',
                ykeys: ['events'],
                labels: ['Événements'],
                pointSize: 2,
                hideHover: 'auto',
                parseTime: false,
                resize: true,
                hoverCallback: function (index, options, content, row) {
                    var customContent = $('<div>' + content + '</div>');
                    $(customContent).find('.morris-hover-row-label').html(row.full_period);
                    return $(customContent).html();
                },
                gridTextFamily: 'Roboto',
                gridTextSize: '14',
            });
        });
    }
});

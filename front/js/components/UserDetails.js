var UserDetails = {
    init: function () {
        $(function () {
            UserDetails.initCharts();
        });
    },
    initCharts: function () {
        $(".chart").css({'height': '350px', 'width': '100%'});

        $('.nav-tabs a:last').tab('show');

        $('.nav-tabs a').click(function (e) {
            e.preventDefault();
            $(this).tab('show');
        });

        UserDetails.initLieux();
        UserDetails.initActivite();
    },
    initActivite: function () {
        UserDetails.chartActivite("annee", ["#67C2EF"]);
        $('#chartMois').click(function () {
            if (!$(this).hasClass("loaded")) {
                $(this).addClass("loaded");
                UserDetails.chartActivite("mois", ["#BDEA74"]);
            }
        });

        $('#chartSemaine').click(function () {
            if (!$(this).hasClass("loaded")) {
                $(this).addClass("loaded");
                UserDetails.chartActivite("semaine", ["#fabb3d"]);
            }
        });
    },
    initLieux: function () {
        var morris_data = [];

        $.each(window.datas, function (i, etablissement) {
            morris_data.push({"label": etablissement.nom || "", "value": etablissement.nbEtablissements});
        });

        Morris.Donut({
            element: 'hero-donut',
            data: morris_data,
            colors: ["#36A9E1", "#bdea74", "#67c2ef", "#fabb3d", "#ff5454"],
            formatter: function (y) {
                return y;
            },
            resize: true
        });
    },
    prepare: function (dataArray) {
        return dataArray.map(function (item, index) {
            return {y: item, myIndex: index};
        });
    },
    prepareActivite: function (datas) {
        return datas.data.map(function (events, index) {
            return {period: datas.categories[index], events: events, full_period: datas.full_categories[index]};
        });
    },
    chartActivite: function (type, colors) {

        var element = "chart-" + type;
        var chart = $("#" + element);
        $.get(chart.data("url")).done(function (datas) {
            chart.children().remove();
            Morris.Area({
                element: element,
                lineColors: colors,
                data: UserDetails.prepareActivite(datas),
                xkey: 'period',
                ykeys: ['events'],
                labels: ['Événements'],
                pointSize: 2,
                hideHover: 'auto',
                parseTime: false,
                resize: true,
                hoverCallback: function (index, options, content, row) {
                    var customContent = $("<div>" + content + "</div>");
                    $(customContent).find('.morris-hover-row-label').html(row.full_period);
                    return $(customContent).html();
                },
                gridTextFamily: 'Roboto',
                gridTextSize: '14'
            });
        });
    }
};

UserDetails.init();
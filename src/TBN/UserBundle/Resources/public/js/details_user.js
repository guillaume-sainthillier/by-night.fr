/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

$(document).ready(function()
{
    init_charts();
});

function init_charts()
{
    var morris_data = [];
    
    $.each(window.datas, function(i, etablissement)
    {
        morris_data.push({"label" : etablissement.nom || "", "value" : etablissement.nbEtablissements}); 
    });

    Morris.Donut({
        element: 'hero-donut',
        data: morris_data,
        colors: ["#36A9E1", "#bdea74", "#67c2ef", "#fabb3d", "#ff5454"],
        formatter: function(y) {
            return y ;
        }
    });

    $(".chart").css({'height' : '350px', 'width': '100%'});
    $('.nav-tabs a:last').tab('show');
    
    chart("annee", ["#67C2EF"]);
    
    $("#chartAnnee").click(function()
    {
        reflow("annee");
    });
	
    $('#chartMois').click(function(){
        if(! $(this).hasClass("loaded"))
        {
            $(this).addClass("loaded");
            chart("mois", ["#BDEA74"]);
        }else
        {
            reflow("mois");
        }        
    });

    $('#chartSemaine').click(function(){
        if(! $(this).hasClass("loaded"))
        {
            $(this).addClass("loaded");
            chart("semaine", ["#fabb3d"]);
        }else
        {
            reflow("semaine");
        }
    });
    
    $('.nav-tabs a').click(function(e)
    {
        e.preventDefault();
        $(this).tab('show');
    });
}

function reflow(type)
{
    var chart = $("#chart-"+type).highcharts();
    chart.reflow();
}

function prepare(dataArray) {
    return dataArray.map(function (item, index) {
        return {y: item, myIndex: index};
    });
};

function chart(type, color) {

    var chart = $("#chart-"+type);
    $.get(chart.data("url"))
    .done(function(datas)
    {
	chart.highcharts({
            chart: {
                type: 'area'
            },
            title: {
                text: '',
                enabled: false
            },
            legend: {
                enabled: false
            },
            xAxis: {
                categories: datas.categories,
                tickmarkPlacement: 'on',
                tickInterval: 1,

                labels: {
                    step: 1
                },
                title: {
                    text: null
                }
            },
            yAxis: {
                title: {
                    enabled: false
                }
            },
            tooltip: {
                formatter: function()
                {
                    return datas.full_categories[this.point.myIndex] + '<br /><b>' + this.y + '</b> événement' + ( this.y > 1 ? "s" : "");
                }
            },
            plotOptions: {
                area: {
                    marker: {
                        enabled: false,
                        symbol: 'circle',
                        radius: 2,
                        states: {
                            hover: {
                                enabled: true
                            }
                        }
                    }
                }
            },
            series: [{
                showInLegend: false,
                name: '',
                data: prepare(datas.data),
                color: color
            }]
        }).find("text:last").remove();
    });
}
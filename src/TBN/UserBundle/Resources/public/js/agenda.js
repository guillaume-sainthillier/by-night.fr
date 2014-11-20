/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */


$(document).ready(function()
{
    //Checkboxs
    $("body").off("hasConnected").on("hasConnected", function(event, ui)
    {
        var label = ui.target;
        var bloc_config = $(label).closest(".bloc_config");
        var ck = bloc_config.find(".onoffswitch-checkbox");
        ck.data("connected", "1").attr('checked',true).addClass("checked");
    }).off("wantDisconnect").on("wantDisconnect", function(event, label)
    {
        var bloc_config = $(label).closest(".bloc_config");
        bloc_config.find(".onoffswitch-checkbox").attr('checked',false).removeClass("checked");
    }).off("wantConnect").on("wantConnect", function(event, label)
    {
        var bloc_config = $(label).closest(".bloc_config");
        var ck = bloc_config.find(".onoffswitch-checkbox");
        
        if(!ck.prop("disabled"))
        {
            if(!ck.data("connected"))
            {
                launch_social_connect(label);
            }else
            {
                bloc_config.find(".onoffswitch-checkbox").attr('checked',true).addClass("checked");
            }
        }        
    });

    //SummerNote
    $("#tbn_agenda_descriptif").summernote({
        lang: 'fr-FR',
        toolbar: [     
            ['heading', ['style']],
            ['style', ['bold', 'italic', 'underline']],
            ['para', ['ul', 'ol', 'paragraph']],
            ['insert', ['link', 'picture', 'video', 'hr']],
            ['misc', ['undo', 'redo', 'fullscreen']]
        ],
        height: 250,
        codemirror: {
            mode: 'text/html',
            htmlMode: true
        }
    });
    
    console.log(ville);
    
    //Google Maps
    var geocoder = new google.maps.Geocoder();
    geocoder.geocode( {'address' : ville}, function(results, status)
    {
        if (status === google.maps.GeocoderStatus.OK)
        {
            var $field = $('#tbn_agenda_adresse');
            console.log(results[0].address_components[1].short_name);
            $field.addresspicker({
                "regionBias": "fr",
                "componentsFilter" : "country:FR|administrative_area:" + results[0].address_components[1].short_name,
                
                mapOptions: {
                    zoom: 12,
                    center: results[0].geometry.location,
                    scrollwheel: true,
                    mapTypeId: google.maps.MapTypeId.ROADMAP
                  },
                "elements":
                {
                    "map":"#map",
                    "lat":"#tbn_agenda_latitude",
                    "lng":"#tbn_agenda_longitude",
                    "locality":"#tbn_agenda_ville",
                    "postal_code": "#tbn_agenda_codePostal"
                },
                "updateCallback" : function(result, b)
                {
                    var rue = b.street_number ? b.street_number : '';
                    rue += b.route ? ' ' + b.route : '';

                    $("#tbn_agenda_rue").val(rue.trim());
                }
            });

            var gmarker = $field.addresspicker( "marker");
            gmarker.setVisible(true);
            $field.addresspicker("updatePosition");

            // Update zoom field
            var map = $field.addresspicker("map");
            google.maps.event.addListener(map, 'idle', function(){
              $('#zoom').val(map.getZoom());
            });

        }
    });

    //Datepicker
    $field = $('#tbn_agenda_dateFin');
    var $configs = $.extend({
        minDate: new Date(2009, 0, 1),
        maxDate: new Date(2019, 11, 31)
    }, $.datepicker.regional['fr'] ,{"dateFormat":"yy-mm-dd"});


    $field.datepicker($configs);
    
    $field = $('#tbn_agenda_dateDebut');
    $field.datepicker($configs);
});
/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */


$(document).ready(function()
{

    //TinyMCE
    var $configs = {
        "language_url" : window.tiny_base_url,
        "mode":"exact",
        "elements":"tbn_agenda_descriptif",

        toolbar1: "bold italic underline strikethrough | alignleft aligncenter alignright alignjustify | formatselect",
        toolbar2: "charmap searchreplace | bullist numlist | outdent indent blockquote | link unlink image | preview",
        menubar: false    
    };
    tinyMCE.init($configs);
    
    //Google Maps

    var geocoder = new google.maps.Geocoder();
    geocoder.geocode( {'address' : ville}, function(results, status)
    {
        if (status === google.maps.GeocoderStatus.OK)
        {
            var $field = $('#tbn_agenda_address');
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
                    "locality":"#tbn_agenda_commune",
                    "postal_code": "#tbn_agenda_codePostal"
                },
                "updateCallback" : function(result, b)
                {
                    $("#tbn_agenda_rue").val((b.street_number ? b.street_number + " " : "" )+b.route);
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
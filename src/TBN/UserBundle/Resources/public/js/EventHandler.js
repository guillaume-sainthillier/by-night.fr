var EventHandler = {
    init: function ()
    {
        $(function ()
        {
            EventHandler.initSocials();
            EventHandler.initWYSIWYG();
            EventHandler.initGMap();
        });
    },
    initSocials: function ()
    {
        //Checkboxs
        $("body").off("hasConnected").on("hasConnected", function (event, ui)
        {
            var ck = ui.target;
            var user = ui.user;            
            var bloc_config = $(ck).closest(".bloc_config");
            
            $(ck).data("connected", "1").prop('checked', true);            
            bloc_config.find(".when_on").html('Connecté sous ' + user.username);            
        }).off("wantDisconnect").on("wantDisconnect", function (event, ck)
        {
            $(ck).prop('checked', false);            
        }).off("wantConnect").on("wantConnect", function (event, ck)
        {
            if (!$(ck).data("connected"))
            {
                SocialLogin.launchSocialConnect(ck);
            } else
            {
                $(ck).prop('checked', true);
            }            
        });
    },
    initWYSIWYG: function ()
    {
        //SummerNote
        $("#tbn_agenda_descriptif").summernote({
            lang: 'fr-FR',
            toolbar: [
                ['heading', ['style']],
                ['style', ['bold', 'italic', 'underline']],
                ['para', ['ul', 'ol', 'paragraph']],
                ['insert', ['link', 'picture', 'video', 'hr']],
                ['misc', ['fullscreen']]
            ],
            height: 280,
            codemirror: {
                mode: 'text/html',
                htmlMode: true
            }
        });
    },
    initGMap: function ()
    {
        //Google Maps
        var geocoder = new google.maps.Geocoder();
        geocoder.geocode({'address': ville}, function (results, status)
        {
            if (status === google.maps.GeocoderStatus.OK && results.length)
            {   
                // instantiate the addressPicker suggestion engine (based on bloodhound)
                var addressPicker = new AddressPicker({
                    map: {
                        id: '#map',
                        zoom: 12,
                        center: results[0].geometry.location,
                        scrollwheel: true,
                        mapTypeId: google.maps.MapTypeId.ROADMAP
                    },
                    autocompleteService: {
                        types: ['geocode'],
                        componentRestrictions: {country: 'FR'}
                    },
                    marker: {
                        draggable: true,
                        visible: false
                    }
                });
                
                var $field = $('#tbn_agenda_adresse');
                // Proxy inputs typeahead events to addressPicker
                addressPicker.bindDefaultTypeaheadEvent($field);
                $(addressPicker).on('addresspicker:selected', function (event, result) {
                    EventHandler.assignGMapInfo(event, result);                    
                });
                
                // instantiate the typeahead UI
                $field.typeahead(null, {
                    displayKey: 'description',
                    source: addressPicker.ttAdapter()
                });
                
                //Lieux
                var $field = $('#tbn_agenda_place_nom');
                // instantiate the placePicker suggestion engine (based on bloodhound)
                var placePicker = new AddressPicker({
                    autocompleteService: {
                        types: ['establishment'],
                        componentRestrictions: {country: 'FR'}
                    }
                });
                
                // Proxy inputs typeahead events to addressPicker
                placePicker.bindDefaultTypeaheadEvent($field);
                $(placePicker).on('addresspicker:selected', function (event, result) {
                    EventHandler.assignGMapInfo(event, result);

                    if(typeof result.placeResult.formatted_address !== "undefined" && result.placeResult.formatted_address)
                    {
                        $('#tbn_agenda_adresse').typeahead('val', result.placeResult.formatted_address);
                        addressPicker.updateMap(event, result.placeResult);
                    }

                    if(typeof result.placeResult.name !== "undefined" && result.placeResult.name)
                    {
                        $field.data('name', result.placeResult.name);
                    }
                });

                $field.typeahead(null, {
                    displayKey: 'description',
                    source: placePicker.ttAdapter()
                }).on('typeahead:selected', function(e, data)
                {
                    $(this).typeahead('val', data.terms[0].value).blur();
                });
            }
        });
    },
    assignGMapInfo: function (event, result)
    {
        $('#tbn_agenda_place_latitude').val(result.lat());
        $('#tbn_agenda_place_longitude').val(result.lng());
        $('#tbn_agenda_place_ville_nom').val(result.nameForType('locality'));
        $('#tbn_agenda_place_ville_codePostal').val(result.nameForType('postal_code'));

        var rue = ((result.nameForType('street_number') ? result.nameForType('street_number') : '') + ' ' + (result.nameForType('route') || '')).trim();
        $('#tbn_agenda_place_rue').val(rue);
    }
};

EventHandler.init();
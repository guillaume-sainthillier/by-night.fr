var UserEventHandler = {
    init: function () {
        $(function () {
            UserEventHandler.initSocials();
            UserEventHandler.initWYSIWYG();
            UserEventHandler.initGMap();

            $(".form-delete form").submit(function () {
                return confirm("Cette action va supprimer l'événement ainsi que toutes les données rattachées. Continuer ?");
            });

            var dateFrom = $("#agenda_dateDebut");
            var dateTo = $("#agenda_dateFin");

            dateTo.datepicker('setStartDate', dateFrom.datepicker('getDate'));
            dateFrom.datepicker('setEndDate', dateTo.datepicker('getDate'));
            
            dateFrom.on('changeDate', function(e) {
                dateTo.datepicker('setStartDate', e.date);
            });

            dateTo.on('changeDate', function(e) {
                dateFrom.datepicker('setEndDate', e.date);
            });
        });
    },
    initSocials: function () {
        //Checkboxs
        $("body").off("hasConnected").on("hasConnected", function (event, ui) {
            var ck = ui.target;
            var user = ui.user;
            var bloc_config = $(ck).closest(".bloc_config");

            $(ck).data("connected", "1").prop('checked', true);
            bloc_config.find(".when_on").html('Connecté sous ' + user.username);
        }).off("wantDisconnect").on("wantDisconnect", function (event, ck) {
            $(ck).prop('checked', false);
        }).off("wantConnect").on("wantConnect", function (event, ck) {
            if (!$(ck).data("connected")) {
                SocialLogin.launchSocialConnect(ck);
            } else {
                $(ck).prop('checked', true);
            }
        });
    },
    initWYSIWYG: function () {
        //SummerNote
        $("#agenda_descriptif").summernote({
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
    initGMap: function () {
        //Google Maps
        // instantiate the addressPicker suggestion engine (based on bloodhound)
        var addressPicker = new AddressPicker({
            map: {
                id: '#map',
                zoom: 12,
                scrollwheel: true,
                center: {
                    lat: 43.6,
                    lng: 1.433333
                },
                mapTypeId: google.maps.MapTypeId.ROADMAP
            },
            autocompleteService: {
                types: ['address']
            },
            marker: {
                draggable: true,
                visible: true
            }
        });

        var $field = $('#agenda_adresse');
        // Proxy inputs typeahead events to addressPicker
        addressPicker.bindDefaultTypeaheadEvent($field);
        $(addressPicker).on('addresspicker:selected', function (event, result) {
            UserEventHandler.assignGMapInfo(event, result);
        });

        // instantiate the typeahead UI
        $field.typeahead(null, {
            displayKey: 'description',
            source: addressPicker.ttAdapter()
        });

        //Lieux
        var $field = $('#agenda_placeName');
        // instantiate the placePicker suggestion engine (based on bloodhound)
        var placePicker = new AddressPicker({
            autocompleteService: {
                types: ['establishment']
            }
        });

        // Proxy inputs typeahead events to addressPicker
        placePicker.bindDefaultTypeaheadEvent($field);
        $(placePicker).on('addresspicker:selected', function (event, result) {
            UserEventHandler.assignGMapInfo(event, result);

            if (typeof result.placeResult.formatted_address !== "undefined" && result.placeResult.formatted_address) {
                $('#agenda_adresse').typeahead('val', result.placeResult.formatted_address);
                addressPicker.updateMap(event, result.placeResult);
            }

            if (typeof result.placeResult.name !== "undefined" && result.placeResult.name) {
                $field.data('name', result.placeResult.name);
            }
        });

        $field.typeahead(null, {
            displayKey: 'description',
            source: placePicker.ttAdapter()
        }).on('typeahead:selected', function (e, data) {
            $(this).typeahead('val', data.terms[0].value).blur();
        });

    },
    assignGMapInfo: function (event, result) {
        $('#agenda_latitude').val(result.lat());
        $('#agenda_longitude').val(result.lng());
        $('#agenda_placeCity').val(result.nameForType('locality'));
        $('#agenda_placePostalCode').val(result.nameForType('postal_code'));

        var rue = ((result.nameForType('street_number') ? result.nameForType('street_number') : '') + ' ' + (result.nameForType('route') || '')).trim();
        $('#agenda_placeStreet').val(rue);
        $("#agenda_placeCountry").val(result.nameForType('country', true) || '');
    }
};

UserEventHandler.init();

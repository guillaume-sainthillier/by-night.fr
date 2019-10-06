import 'summernote/dist/summernote-bs4.css';

import 'summernote/dist/summernote-bs4';
import 'summernote/dist/lang/summernote-fr-FR';

export default class UserEventHandler {
    init() {
        const self = this;
        $(function () {
            self.initWYSIWYG();
            self.initGMap();

            $(".form-delete form").submit(function () {
                return confirm("Cette action va supprimer l'événement ainsi que toutes les données rattachées. Continuer ?");
            });

            var dateFrom = $("#event_dateDebut");
            var dateTo = $("#event_dateFin");

            dateTo.datepicker('setStartDate', dateFrom.datepicker('getDate'));
            dateFrom.datepicker('setEndDate', dateTo.datepicker('getDate'));

            dateFrom.on('changeDate', function (e) {
                dateTo.datepicker('setStartDate', e.date);
            });

            dateTo.on('changeDate', function (e) {
                dateFrom.datepicker('setEndDate', e.date);
            });
        });
    }

    initWYSIWYG() {
        //SummerNote
        $("#event_descriptif").summernote({
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
    }

    initGMap() {
        const self = this;
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

        var $field = $('#event_adresse');
        // Proxy inputs typeahead events to addressPicker
        addressPicker.bindDefaultTypeaheadEvent($field);
        $(addressPicker).on('addresspicker:selected', function (event, result) {
            self.assignGMapInfo(event, result);
        });

        // instantiate the typeahead UI
        $field.typeahead(null, {
            displayKey: 'description',
            source: addressPicker.ttAdapter()
        });

        //Lieux
        var $field = $('#event_placeName');
        // instantiate the placePicker suggestion engine (based on bloodhound)
        var placePicker = new AddressPicker({
            autocompleteService: {
                types: ['establishment']
            }
        });

        // Proxy inputs typeahead events to addressPicker
        placePicker.bindDefaultTypeaheadEvent($field);
        $(placePicker).on('addresspicker:selected', function (event, result) {
            self.assignGMapInfo(event, result);

            if (typeof result.placeResult.formatted_address !== "undefined" && result.placeResult.formatted_address) {
                $('#event_adresse').typeahead('val', result.placeResult.formatted_address);
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

    }

    assignGMapInfo(event, result) {
        $('#event_latitude').val(result.lat());
        $('#event_longitude').val(result.lng());
        $('#event_placeCity').val(result.nameForType('locality'));
        $('#event_placePostalCode').val(result.nameForType('postal_code'));

        var rue = ((result.nameForType('street_number') ? result.nameForType('street_number') : '') + ' ' + (result.nameForType('route') || '')).trim();
        $('#event_placeStreet').val(rue);
        $("#event_placeCountry").val(result.nameForType('country', true) || '').trigger('change');
    }
};
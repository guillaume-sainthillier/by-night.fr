import '../../scss/pages/espace_perso_event.scss';

import $ from 'jquery';
import 'summernote/dist/summernote-bs5.css';
import 'summernote/dist/summernote-bs5';
import 'summernote/dist/lang/summernote-fr-FR';

import initDates from '../lazy-listeners/dates';
import initImagePreview from '../lazy-listeners/image-previews';
import initSelects from '../lazy-listeners/selects';
import initTypeAHead from '../lazy-listeners/typeahead';
import 'typeahead-addresspicker/dist/typeahead-addresspicker';

$(document).ready(function () {
    initDates();
    initSelects();
    initTypeAHead();
    initImagePreview();

    init();
});

function init() {
    initWYSIWYG();
    initGMap();

    $('select.form-select:not(.hidden)').select2({
        theme: 'bootstrap-5',
        minimumResultsForSearch: 10,
        width: $(this).data('width') ? $(this).data('width') : $(this).hasClass('w-100') ? '100%' : 'style',
    });

    $('.form-delete form').submit(function () {
        return confirm("Cette action va supprimer l'événement ainsi que toutes les données rattachées. Continuer ?");
    });
}

function initWYSIWYG() {
    //SummerNote
    $('#app_event_description').summernote({
        lang: 'fr-FR',
        toolbar: [
            ['heading', ['style']],
            ['style', ['bold', 'italic', 'underline']],
            ['para', ['ul', 'ol', 'paragraph']],
            ['insert', ['link', 'picture', 'video', 'hr']],
            ['misc', ['fullscreen']],
        ],
        height: 280,
        codemirror: {
            mode: 'text/html',
            htmlMode: true,
        },
    });
}

function initGMap() {
    //Google Maps
    // instantiate the addressPicker suggestion engine (based on bloodhound)
    var addressPicker = new AddressPicker({
        map: {
            id: '#map',
            zoom: 12,
            scrollwheel: true,
            center: {
                lat: 43.6,
                lng: 1.433333,
            },
            mapTypeId: google.maps.MapTypeId.ROADMAP,
        },
        autocompleteService: {
            types: ['address'],
        },
        marker: {
            draggable: true,
            visible: true,
        },
    });

    var $field = $('#app_event_address');
    // Proxy inputs typeahead events to addressPicker
    addressPicker.bindDefaultTypeaheadEvent($field);
    $(addressPicker).on('addresspicker:selected', function (event, result) {
        assignGMapInfo(event, result);
    });

    // instantiate the typeahead UI
    $field.typeahead(null, {
        displayKey: 'description',
        source: addressPicker.ttAdapter(),
    });

    //Lieux
    var $field = $('#app_event_place_name');
    // instantiate the placePicker suggestion engine (based on bloodhound)
    var placePicker = new AddressPicker({
        autocompleteService: {
            types: ['establishment'],
        },
    });

    // Proxy inputs typeahead events to addressPicker
    placePicker.bindDefaultTypeaheadEvent($field);
    $(placePicker).on('addresspicker:selected', function (event, result) {
        assignGMapInfo(event, result);

        if (typeof result.placeResult.formatted_address !== 'undefined' && result.placeResult.formatted_address) {
            $('#app_event_address').typeahead('val', result.placeResult.formatted_address);
            addressPicker.updateMap(event, result.placeResult);
        }

        if (typeof result.placeResult.name !== 'undefined' && result.placeResult.name) {
            $field.data('name', result.placeResult.name);
        }
    });

    $field
        .typeahead(null, {
            displayKey: 'description',
            source: placePicker.ttAdapter(),
        })
        .on('typeahead:selected', function (e, data) {
            $(this).typeahead('val', data.terms[0].value).blur();
        });
}

function assignGMapInfo(event, result) {
    $('#app_event_place_latitude').val(result.lat());
    $('#app_event_place_longitude').val(result.lng());
    $('#app_event_place_city_name').val(result.nameForType('locality'));
    $('#app_event_place_city_postalCode').val(result.nameForType('postal_code'));

    var rue = (
        (result.nameForType('street_number') ? result.nameForType('street_number') : '') +
        ' ' +
        (result.nameForType('route') || '')
    ).trim();
    $('#event_placeStreet').val(rue);
    $('#app_event_place_country')
        .val(result.nameForType('country', true) || '')
        .trigger('change');
}

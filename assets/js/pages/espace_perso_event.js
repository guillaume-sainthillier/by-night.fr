import $ from 'jquery'

import initDates from '@/js/lazy-listeners/dates'
import initImagePreview from '@/js/lazy-listeners/image-previews'
import initSelects from '@/js/lazy-listeners/selects'
import initTypeAHead from '@/js/lazy-listeners/typeahead'
import initWYSIWYG from '@/js/lazy-listeners/wysiwyg'

import 'typeahead-addresspicker/dist/typeahead-addresspicker'

$(document).ready(function () {
    initDates()
    initSelects()
    initTypeAHead()
    initImagePreview()
    initWYSIWYG()

    init()
})

function init() {
    initGMap()

    $('.form-delete form').submit(function () {
        return window.confirm(
            "Cette action va supprimer l'événement ainsi que toutes les données rattachées. Continuer ?"
        )
    })
}

function initGMap() {
    // Google Maps

    // Lieux
    const $field = $('#app_event_place_name')
    // instantiate the addressPicker suggestion engine (based on bloodhound)
    const addressPicker = new window.AddressPicker({
        map: {
            id: '#map',
            zoom: 12,
            scrollwheel: true,
            center: {
                lat: 43.6,
                lng: 1.433333,
            },
            mapTypeId: window.google.maps.MapTypeId.ROADMAP,
        },
        autocompleteService: {
            types: ['address'],
        },
        marker: {
            draggable: true,
            visible: true,
        },
    })

    const $addressField = $('#app_event_address')
    // Proxy inputs typeahead events to addressPicker
    addressPicker.bindDefaultTypeaheadEvent($field)
    $(addressPicker).on('addresspicker:selected', function (event, result) {
        assignGMapInfo(event, result)
    })

    // instantiate the typeahead UI
    $addressField.typeahead(null, {
        displayKey: 'description',
        source: addressPicker.ttAdapter(),
    })
    // instantiate the placePicker suggestion engine (based on bloodhound)
    const placePicker = new window.AddressPicker({
        autocompleteService: {
            types: ['establishment'],
        },
    })

    // Proxy inputs typeahead events to addressPicker
    placePicker.bindDefaultTypeaheadEvent($field)
    $(placePicker).on('addresspicker:selected', function (event, result) {
        assignGMapInfo(event, result)

        if (typeof result.placeResult.formatted_address !== 'undefined' && result.placeResult.formatted_address) {
            $('#app_event_address').typeahead('val', result.placeResult.formatted_address)
            addressPicker.updateMap(event, result.placeResult)
        }

        if (typeof result.placeResult.name !== 'undefined' && result.placeResult.name) {
            $field.data('name', result.placeResult.name)
        }
    })

    $field
        .typeahead(null, {
            displayKey: 'description',
            source: placePicker.ttAdapter(),
        })
        .on('typeahead:selected', function (e, data) {
            $(this).typeahead('val', data.terms[0].value).blur()
        })
}

function assignGMapInfo(event, result) {
    $('#app_event_place_latitude').val(result.lat())
    $('#app_event_place_longitude').val(result.lng())
    $('#app_event_place_city_name').val(result.nameForType('locality'))
    $('#app_event_place_city_postalCode').val(result.nameForType('postal_code'))

    const rue = `${result.nameForType('street_number') ? result.nameForType('street_number') : ''} ${
        result.nameForType('route') || ''
    }`.trim()
    $('#event_placeStreet').val(rue)
    $('#app_event_place_country')
        .val(result.nameForType('country', true) || '')
        .trigger('change')
}

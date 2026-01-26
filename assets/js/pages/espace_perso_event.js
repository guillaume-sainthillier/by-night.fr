import $ from 'jquery'
import TomSelect from 'tom-select'

import initDates from '@/js/lazy-listeners/dates'
import initImagePreview from '@/js/lazy-listeners/image-previews'
import initSelects from '@/js/lazy-listeners/selects'
import initTags from '@/js/lazy-listeners/tags'
import initWYSIWYG from '@/js/lazy-listeners/wysiwyg'
import initEventScheduler from '@/js/listeners/event-scheduler'
import initTimesheetHoursSync from '@/js/listeners/timesheet-hours-sync'

$(document).ready(function () {
    initDates()
    initSelects()
    initTags()
    initImagePreview()
    initWYSIWYG()

    // Initialize event scheduler
    const di = window.App
    initEventScheduler(document.body, di)

    // Initialize timesheet hours sync
    initTimesheetHoursSync(document.body)

    init()
})

function init() {
    initGMap()

    $('.form-delete form').submit(function () {
        return window.confirm(
            "Cette action va supprimer l'\u00e9v\u00e9nement ainsi que toutes les donn\u00e9es rattach\u00e9es. Continuer ?"
        )
    })
}

async function initGMap() {
    const { Map } = await window.google.maps.importLibrary('maps')
    const { AdvancedMarkerElement } = await window.google.maps.importLibrary('marker')
    const { AutocompleteService, Place } = await window.google.maps.importLibrary('places')

    const autocompleteService = new AutocompleteService()
    const mapEl = document.getElementById('map')
    const defaultCenter = { lat: 43.6, lng: 1.433333 }

    const map = new Map(mapEl, {
        center: defaultCenter,
        zoom: 12,
        mapId: mapEl.dataset.mapId,
        mapTypeId: window.google.maps.MapTypeId.ROADMAP,
    })

    const marker = new AdvancedMarkerElement({
        map,
        position: defaultCenter,
        gmpDraggable: true,
    })

    // French-speaking countries and territories
    const allowedCountries = ['FR', 'BE', 'CH', 'MC', 'GF', 'GP', 'MQ', 'RE', 'YT']

    async function fetchPredictions(query, types) {
        const { predictions } = await autocompleteService.getPlacePredictions({
            input: query,
            types,
            componentRestrictions: { country: allowedCountries },
            language: 'fr',
            maxResults: 10,
        })
        return predictions || []
    }

    async function fetchPlaceDetails(prediction) {
        const place = new Place({ id: prediction.place_id })
        await place.fetchFields({
            fields: ['displayName', 'formattedAddress', 'location', 'addressComponents'],
        })
        return place
    }

    function updateMap(lat, lng) {
        map.setCenter({ lat, lng })
        marker.position = { lat, lng }
        $('#app_event_place_latitude').val(lat)
        $('#app_event_place_longitude').val(lng)
    }

    function assignAddressComponents(components) {
        if (!components) return
        let city = '',
            postalCode = '',
            streetNumber = '',
            route = '',
            country = ''
        for (const c of components) {
            if (c.types.includes('locality')) city = c.longText
            else if (c.types.includes('postal_code')) postalCode = c.longText
            else if (c.types.includes('street_number')) streetNumber = c.longText
            else if (c.types.includes('route')) route = c.longText
            else if (c.types.includes('country')) country = c.shortText
        }
        $('#app_event_place_city_name').val(city)
        $('#app_event_place_city_postalCode').val(postalCode)
        $('#app_event_place_street').val(`${streetNumber} ${route}`.trim())
        $('#app_event_place_country').val(country).trigger('change')
    }

    async function reverseGeocode(lat, lng) {
        const geocoder = new window.google.maps.Geocoder()
        try {
            const { results } = await geocoder.geocode({ location: { lat, lng } })
            if (!results?.[0]) return
            const r = results[0]
            addressSelect.setTextboxValue(r.formatted_address)
            $('#app_event_address').val(r.formatted_address)
            let city = '',
                postalCode = '',
                streetNumber = '',
                route = '',
                country = ''
            for (const c of r.address_components) {
                if (c.types.includes('locality')) city = c.long_name
                if (c.types.includes('postal_code')) postalCode = c.long_name
                if (c.types.includes('street_number')) streetNumber = c.long_name
                if (c.types.includes('route')) route = c.long_name
                if (c.types.includes('country')) country = c.short_name
            }
            $('#app_event_place_city_name').val(city)
            $('#app_event_place_city_postalCode').val(postalCode)
            $('#app_event_place_street').val(`${streetNumber} ${route}`.trim())
            $('#app_event_place_country').val(country).trigger('change')
        } catch (e) {
            console.error('Reverse geocode failed:', e)
        }
    }

    // Place name autocomplete (establishments)
    new TomSelect('#app_event_place_name', {
        valueField: 'main_text',
        labelField: 'main_text',
        searchField: 'main_text',
        plugins: ['remove_button'],
        maxItems: 1,
        create: true,
        createFilter: () => true,
        loadThrottle: 250,
        load(query, callback) {
            fetchPredictions(query, ['establishment'])
                .then((predictions) =>
                    callback(
                        predictions.map((p) => ({
                            place_id: p.place_id,
                            main_text: p.structured_formatting.main_text,
                            secondary_text: p.structured_formatting.secondary_text || '',
                            _raw: p,
                        }))
                    )
                )
                .catch(() => callback())
        },
        render: {
            option(data, escape) {
                return `<div><strong>${escape(data.main_text)}</strong>
                    <small class="text-muted ms-2">${escape(data.secondary_text ?? '')}</small></div>`
            },
            item(data, escape) {
                return `<div>${escape(data.main_text)}</div>`
            },
            no_results() {
                return '<div class="no-results">Aucun r\u00e9sultat</div>'
            },
        },
        async onChange(value) {
            if (!value) return
            const option = this.options[value]
            if (!option?._raw) return
            const place = await fetchPlaceDetails(option._raw)
            if (place.formattedAddress) {
                addressSelect.setTextboxValue(place.formattedAddress)
                $('#app_event_address').val(place.formattedAddress)
            }
            if (place.location) updateMap(place.location.lat(), place.location.lng())
            assignAddressComponents(place.addressComponents)
        },
    })

    // Address autocomplete
    const addressSelect = new TomSelect('#app_event_address', {
        valueField: 'description',
        labelField: 'description',
        searchField: 'description',
        plugins: ['remove_button'],
        maxItems: 1,
        create: true,
        createFilter: () => true,
        loadThrottle: 250,
        load(query, callback) {
            fetchPredictions(query, ['address'])
                .then((predictions) =>
                    callback(
                        predictions.map((p) => ({
                            place_id: p.place_id,
                            description: p.description,
                            _raw: p,
                        }))
                    )
                )
                .catch(() => callback())
        },
        render: {
            option(data, escape) {
                return `<div>${escape(data.description)}</div>`
            },
            item(data, escape) {
                return `<div>${escape(data.description)}</div>`
            },
            no_results() {
                return '<div class="no-results">Aucun r\u00e9sultat</div>'
            },
        },
        async onChange(value) {
            if (!value) return
            const option = this.options[value]
            if (!option?._raw) return
            const place = await fetchPlaceDetails(option._raw)
            if (place.location) updateMap(place.location.lat(), place.location.lng())
            assignAddressComponents(place.addressComponents)
        },
    })

    // Marker drag -> reverse geocode
    marker.addListener('dragend', () => {
        const { lat, lng } = marker.position
        updateMap(lat, lng)
        reverseGeocode(lat, lng)
    })

    // Pre-populate map in edit mode
    const lat = parseFloat($('#app_event_place_latitude').val())
    const lng = parseFloat($('#app_event_place_longitude').val())
    if (!isNaN(lat) && !isNaN(lng) && (lat !== 0 || lng !== 0)) {
        map.setCenter({ lat, lng })
        marker.position = { lat, lng }
    }
}

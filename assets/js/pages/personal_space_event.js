import $ from 'jquery'
import TomSelect from 'tom-select'
import { setOptions, importLibrary } from '@googlemaps/js-api-loader'

import initDates from '@/js/lazy-listeners/dates'
import initImagePreview from '@/js/lazy-listeners/image-previews'
import initSelects, {initRefreshableSelects} from '@/js/lazy-listeners/selects'
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

    $('.form-delete form').submit(function () {
        return window.confirm(
            "Cette action va supprimer l'\u00e9v\u00e9nement ainsi que toutes les donn\u00e9es rattach\u00e9es. Continuer ?"
        )
    })

    // Initialize event scheduler
    const di = window.App
    initEventScheduler(document.body, di)

    // Initialize timesheet hours sync
    initTimesheetHoursSync(document.body)

    // Reinitialize date pickers when new timesheet items are added
    const timesheetsCollection = document.getElementById('app_event_timesheets')
    if (timesheetsCollection) {
        timesheetsCollection.addEventListener('collection.added', (e) => {
            const newItem = e.detail?.item
            if (newItem) {
                initDates(newItem)
            }
        })
    }

    initGMap()

    const eventAddress = document.getElementById('app_event_address')
    const eventPlaceName = document.getElementById('app_event_place_name')
    const eventPlaceLatitude = document.getElementById('app_event_place_latitude')
    const eventPlaceLongitude = document.getElementById('app_event_place_longitude')
    const eventPlaceStreet = document.getElementById('app_event_place_street')
    const eventPlaceCityName = document.getElementById('app_event_place_city_name')
    const eventPlaceCityPostalCode = document.getElementById('app_event_place_city_postalCode')
    const eventPlaceCountry = document.getElementById('app_event_place_country')

    async function initGMap() {
        const mapEl = document.getElementById('map')

        // Configure the Google Maps API loader
        setOptions({
            key: mapEl.dataset.apiKey,
            v: 'weekly',
            language: 'fr',
        })

        // Load required libraries
        const {Map} = await importLibrary('maps')
        const {AdvancedMarkerElement} = await importLibrary('marker')
        const {AutocompleteSuggestion, AutocompleteSessionToken} = await importLibrary('places')

        const defaultCenter = {lat: 43.6, lng: 1.433333}

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

        // Session tokens for billing optimization
        let placeSessionToken = new AutocompleteSessionToken()
        let addressSessionToken = new AutocompleteSessionToken()

        async function fetchPredictions(query, types, sessionToken) {
            try {
                const request = {
                    input: query,
                    region: 'fr', // Bias results towards France
                    language: 'fr',
                    sessionToken,
                }
                // Only add includedPrimaryTypes if types are specified
                if (types && types.length > 0) {
                    request.includedPrimaryTypes = types
                }
                const response = await AutocompleteSuggestion.fetchAutocompleteSuggestions(request)
                return response.suggestions || []
            } catch (error) {
                console.error('Autocomplete error:', error)
                return []
            }
        }

        async function fetchPlaceDetails(placePrediction) {
            // Convert prediction to Place and fetch details
            // The session token is automatically included from the original prediction
            const place = placePrediction.toPlace()
            await place.fetchFields({
                fields: ['displayName', 'formattedAddress', 'location', 'addressComponents'],
            })
            return place
        }

        function updateMap(lat, lng) {
            map.setCenter({lat, lng})
            marker.position = {lat, lng}
            $(eventPlaceLatitude).val(lat)
            $(eventPlaceLongitude).val(lng)
        }

        function assignAddressComponents(components) {
            if (!components) return
            let city = '',
                postalCode = '',
                streetNumber = '',
                route = '',
                country = ''
            for (const c of components) {
                // New API uses longText/shortText, old geocoder uses long_name/short_name
                const longValue = c.longText || c.long_name
                const shortValue = c.shortText || c.short_name
                if (c.types.includes('locality')) city = longValue
                else if (c.types.includes('postal_code')) postalCode = longValue
                else if (c.types.includes('street_number')) streetNumber = longValue
                else if (c.types.includes('route')) route = longValue
                else if (c.types.includes('country')) country = shortValue
            }
            $(eventPlaceCityName).val(city)
            $(eventPlaceCityPostalCode).val(postalCode)
            $(eventPlaceStreet).val(`${streetNumber} ${route}`.trim())
            $(eventPlaceCountry).val(country).trigger('refresh')
        }

        async function reverseGeocode(lat, lng) {
            const geocoder = new window.google.maps.Geocoder()
            try {
                const {results} = await geocoder.geocode({location: {lat, lng}})
                if (!results?.[0]) return
                const r = results[0]
                addressSelect.addOption({ description: r.formatted_address })
                $(eventAddress).val(r.formatted_address).trigger('refresh')
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
                $(eventPlaceCityName).val(city)
                $(eventPlaceCityPostalCode).val(postalCode)
                $(eventPlaceStreet).val(`${streetNumber} ${route}`.trim())
                $(eventPlaceCountry).val(country).trigger('refresh')
            } catch (e) {
                console.error('Reverse geocode failed:', e)
            }
        }

        // Place name autocomplete (establishments)
        new TomSelect(eventPlaceName, {
            valueField: 'main_text',
            labelField: 'main_text',
            searchField: 'main_text',
            plugins: ['remove_button'],
            maxItems: 1,
            create: true,
            createFilter: () => true,
            loadThrottle: 250,
            load(query, callback) {
                fetchPredictions(query, ['establishment'], placeSessionToken)
                    .then((suggestions) =>
                        callback(
                            suggestions.map((s) => {
                                const pred = s.placePrediction
                                const fullText = pred.text.text
                                // Split on first comma to get main/secondary text
                                const parts = fullText.split(',')
                                return {
                                    main_text: parts[0].trim(),
                                    secondary_text: parts.slice(1).join(',').trim(),
                                    _raw: pred,
                                }
                            })
                        )
                    )
                    .catch(() => callback())
            },
            render: {
                option(data, escape) {
                    return `<div>
                    <strong>${escape(data.main_text)}</strong>
                    <small class="text-muted ms-2">${escape(data.secondary_text ?? '')}</small>
                </div>`
                },
                item(data, escape) {
                    return `<div>${escape(data.main_text)}</div>`
                },
                no_results() {
                    return '<div class="no-results">Aucun résultat</div>'
                },
            },
            async onChange(value) {
                if (!value) return
                const option = this.options[value]
                if (!option?._raw) return
                const place = await fetchPlaceDetails(option._raw)
                // Reset session token after fetching details
                placeSessionToken = new AutocompleteSessionToken()
                if (place.formattedAddress) {
                    addressSelect.addOption({ description: place.formattedAddress })
                    $(eventAddress).val(place.formattedAddress).trigger('refresh')
                }
                if (place.location) updateMap(place.location.lat(), place.location.lng())
                assignAddressComponents(place.addressComponents)
            },
        })
        initRefreshableSelects(eventPlaceName)

        // Address autocomplete
        const addressSelect = new TomSelect(eventAddress, {
            valueField: 'description',
            labelField: 'description',
            searchField: 'description',
            plugins: ['remove_button'],
            maxItems: 1,
            create: true,
            createFilter: () => true,
            loadThrottle: 250,
            load(query, callback) {
                fetchPredictions(query, ['address'], addressSessionToken)
                    .then((suggestions) =>
                        callback(
                            suggestions.map((s) => ({
                                description: s.placePrediction.text.text,
                                _raw: s.placePrediction,
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
                // Reset session token after fetching details
                addressSessionToken = new AutocompleteSessionToken()
                if (place.location) updateMap(place.location.lat(), place.location.lng())
                assignAddressComponents(place.addressComponents)
            },
        })
        initRefreshableSelects(eventAddress)

        // Marker drag -> reverse geocode
        marker.addListener('dragend', () => {
            const {lat, lng} = marker.position
            updateMap(lat, lng)
            reverseGeocode(lat, lng)
        })

        // Pre-populate map in edit mode
        const lat = parseFloat($(eventPlaceLatitude).val())
        const lng = parseFloat($(eventPlaceLongitude).val())
        if (!isNaN(lat) && !isNaN(lng) && (lat !== 0 || lng !== 0)) {
            map.setCenter({lat, lng})
            marker.position = {lat, lng}
        }
    }
})

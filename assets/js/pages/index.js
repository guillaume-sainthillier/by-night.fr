import $ from 'jquery'
import { create as createAutocomplete } from '@/js/services/ui/AutocompleteService'

/** @type {Page} */
function initialize({ apiCityURL, agendaURL }) {
    $('.form-city-picker').each(function () {
        const form = $(this)
        const btn = form.find('.choose-city-action')
        const field = form.find('[data-city-name]')[0]
        const cityValue = form.find('[data-city-slug]')[0]

        // The search goes to the agenda of the picked city: its path names the city
        function update() {
            const slug = cityValue.value
            btn.attr('disabled', slug.length === 0)
            if (slug.length > 0) {
                form.attr('action', agendaURL.replace('__LOCATION__', encodeURIComponent(slug)))
            }
        }

        update()

        form.submit(() => !btn.attr('disabled'))

        createAutocomplete({
            element: field,
            url: apiCityURL,
            valueInput: cityValue,
            throttle: 0,
            // No submit on selection: the "Quand ?" chips come after the city
            onSelection: () => {
                update()
                btn.trigger('focus')
            },
            onInput: () => {
                update()
            },
        })
    })
}

window.App.registerPage('index', initialize)

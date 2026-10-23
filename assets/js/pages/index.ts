import $ from 'jquery'
import { create as createAutocomplete } from '@/js/services/ui/AutocompleteService'
import type { Page } from '@/js/types'

const initialize: Page<{ apiCityURL: string; agendaURL: string }> = ({ apiCityURL, agendaURL }) => {
    $('.form-city-picker').each((_index, element) => {
        const form = $(element)
        const btn = form.find('.choose-city-action')
        const field = form.find<HTMLInputElement>('[data-city-name]')[0]
        const cityValue = form.find<HTMLInputElement>('[data-city-slug]')[0]

        // The search goes to the agenda of the picked city: its path names the city
        function update() {
            const slug = cityValue.value
            btn.prop('disabled', slug.length === 0)
            if (slug.length > 0) {
                // A city under its country keeps the slash of its slug ("suisse/geneve"): each segment is encoded
                const location = slug.split('/').map(encodeURIComponent).join('/')
                form.attr('action', agendaURL.replace('__LOCATION__', location))
            }
        }

        update()

        form.submit(() => !btn.prop('disabled'))

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

import { definePage } from '@silarhi/app-js'
import { create as createAutocomplete } from '@/js/services/ui/AutocompleteService'

export default definePage<{ apiCityURL: string; agendaURL: string }>(({ apiCityURL, agendaURL }) => {
    for (const form of document.querySelectorAll<HTMLFormElement>('.form-city-picker')) {
        const btn = form.querySelector<HTMLButtonElement>('.choose-city-action')
        const field = form.querySelector<HTMLInputElement>('[data-city-name]')
        const cityValue = form.querySelector<HTMLInputElement>('[data-city-slug]')
        if (!btn || !field || !cityValue) {
            continue
        }

        // The search goes to the agenda of the picked city: its path names the city
        const update = () => {
            const slug = cityValue.value
            btn.disabled = slug.length === 0
            if (slug.length > 0) {
                // A city under its country keeps the slash of its slug ("suisse/geneve"): each segment is encoded
                const location = slug.split('/').map(encodeURIComponent).join('/')
                form.action = agendaURL.replace('__LOCATION__', location)
            }
        }

        update()

        form.addEventListener('submit', (e) => {
            if (btn.disabled) {
                e.preventDefault()
            }
        })

        createAutocomplete({
            element: field,
            url: apiCityURL,
            valueInput: cityValue,
            throttle: 0,
            // No submit on selection: the "Quand ?" chips come after the city
            onSelection: () => {
                update()
                btn.focus()
            },
            onInput: () => {
                update()
            },
        })
    }
})

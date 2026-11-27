import { defineListener } from '@silarhi/app-js'
import { path } from '@/js/router'

/**
 * The header's city switcher (fragments/header.html.twig): the field of its dropdown suggests the cities as one types,
 * and picking one opens its page, which is its agenda. The autocomplete loads with the first focus, the dropdown
 * focuses the field as it opens.
 */
export default defineListener<HTMLInputElement>({
    selector: 'input[data-city-switcher]',
    connect(input) {
        const dropdown = input.closest('.dropdown')
        let created = false

        // A city under its country keeps the slash of its slug ("suisse/geneve"), as path() does in Twig
        const open = (slug: string) => window.location.assign(path('app_location_index', { location: slug }))

        const create = async () => {
            if (created) {
                return
            }
            created = true
            const { create: createAutocomplete } = await import('@/js/services/ui/AutocompleteService')
            createAutocomplete({
                element: input,
                url: (q: string) => path('api_cities', { _query: { q } }),
                onSelection: (city: { slug: string }) => open(city.slug),
            })
        }

        const focus = () => input.focus()

        input.addEventListener('focus', create)
        dropdown?.addEventListener('shown.bs.dropdown', focus)

        return () => {
            input.removeEventListener('focus', create)
            dropdown?.removeEventListener('shown.bs.dropdown', focus)
        }
    },
})

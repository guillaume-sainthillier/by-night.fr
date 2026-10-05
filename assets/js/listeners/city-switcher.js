/**
 * The header's city switcher (fragments/header.html.twig): the field of its dropdown suggests the cities as one types,
 * and picking one opens its page, which is its agenda. The autocomplete loads with the first focus, the dropdown
 * focuses the field as it opens.
 *
 * @type {Listener}
 */
export default {
    selector: 'input[data-city-switcher]',
    connect(input) {
        const { citiesUrl, locationUrl } = input.dataset
        const dropdown = input.closest('.dropdown')
        let created = false

        // A city under its country keeps the slash of its slug ("suisse/geneve"): each segment is encoded
        const open = (slug) => {
            window.location.assign(
                locationUrl.replace('__LOCATION__', slug.split('/').map(encodeURIComponent).join('/'))
            )
        }

        const create = async () => {
            if (created) {
                return
            }
            created = true
            const { create: createAutocomplete } = await import('@/js/services/ui/AutocompleteService')
            createAutocomplete({
                element: input,
                url: citiesUrl,
                onSelection: (city) => open(city.slug),
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
}

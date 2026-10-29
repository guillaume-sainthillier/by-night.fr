import { defineModule } from '@silarhi/app-js'
import initAutocomplete from '@/js/components/autocomplete'
import { isTouchDevice } from '@/js/utils/utils'

/**
 * Wire the global search autocomplete. Runs once at App.start().
 */
export default defineModule(function autocomplete() {
    const autocompleteEl = document.getElementById('autocomplete')
    if (!autocompleteEl) {
        return
    }

    const { searchUrl: globalSearchUrl, searchPageUrl, suggestionsUrl } = autocompleteEl.dataset
    if (!globalSearchUrl) {
        console.error('Search URL not found in autocomplete element')
        return
    }

    if (!searchPageUrl) {
        console.error('Search page URL not found in autocomplete element')
        return
    }

    const result = initAutocomplete({
        autocompleteSelector: '#autocomplete',
        suggestionsUrl,
        searchPlaceholder: 'Recherche',
        inputPlaceholder: 'Rechercher des événements, villes, membres…',
        globalSearchUrl,
        searchPageUrl,
        enableHotkeys: !isTouchDevice(),
    })

    document.getElementById('autocomplete-mobile-toggler')?.addEventListener('click', (event) => {
        event.preventDefault()
        result?.show()
    })
})

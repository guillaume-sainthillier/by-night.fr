import initAutocomplete from '@/js/components/autocomplete'
import $ from 'jquery'

export default () => {
    const $autocomplete = $('#autocomplete')
    if ($autocomplete.length === 0) {
        return
    }

    const globalSearchUrl = $autocomplete.data('searchUrl')
    if (!globalSearchUrl) {
        console.error('Search URL not found in autocomplete element')
        return
    }

    const searchPageUrl = $autocomplete.data('searchPageUrl')
    if (!searchPageUrl) {
        console.error('Search page URL not found in autocomplete element')
        return
    }

    return initAutocomplete({
        autocompleteSelector: '#autocomplete',
        searchPlaceholder: 'Recherche',
        inputPlaceholder: 'Rechercher des événements, villes, membres...',
        globalSearchUrl,
        searchPageUrl,
        enableHotkeys: true,
    })
}

import initAutocomplete from '@/js/components/autocomplete'
import $ from 'jquery'
import {isTouchDevice} from "@/js/utils/utils"

export default () => {
    const $autocomplete = $('#autocomplete')
    const $autocompleteMobileToggler = $('#autocomplete-mobile-toggler')
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

    const result = initAutocomplete({
        autocompleteSelector: '#autocomplete',
        searchPlaceholder: 'Recherche',
        inputPlaceholder: 'Rechercher des événements, villes, membres...',
        globalSearchUrl,
        searchPageUrl,
        enableHotkeys: !isTouchDevice(),
    })

    $autocompleteMobileToggler.click(() => {
        result.show()
    })

    return result
}

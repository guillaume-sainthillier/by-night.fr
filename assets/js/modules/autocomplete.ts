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

    const result = initAutocomplete({
        autocompleteSelector: '#autocomplete',
        city: autocompleteEl.dataset.city ?? null,
        searchPlaceholder: 'Recherche',
        inputPlaceholder: 'Rechercher des événements, villes, membres…',
        enableHotkeys: !isTouchDevice(),
    })

    document.getElementById('autocomplete-mobile-toggler')?.addEventListener('click', (event) => {
        event.preventDefault()
        result?.show()
    })
})

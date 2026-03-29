import Swal from 'sweetalert2'
import { create } from '@/js/services/ui/AutocompleteService'

export default (di, container) => {
    container.querySelectorAll('.js-impersonate').forEach((el) => {
        el.addEventListener('click', async (e) => {
            e.preventDefault()

            const apiUrl = el.dataset.url
            let autocomplete = null

            const result = await Swal.fire({
                title: 'Impersonation',
                input: 'text',
                inputLabel: 'Rechercher un utilisateur',
                inputPlaceholder: 'Username ou email...',
                showCancelButton: true,
                cancelButtonText: 'Annuler',
                confirmButtonText: 'Impersonifier',
                heightAuto: false,
                didOpen() {
                    autocomplete = create({
                        element: '#swal2-input',
                        url: apiUrl,
                        valueField: 'username',
                        labelField: 'username',
                        minLength: 2,
                        inModal: true,
                        wrapper: false,
                        maxResults: 10,
                        noResultsText: 'Aucun utilisateur trouvé',
                    })
                },
                didClose() {
                    autocomplete?.destroy()
                },
            })

            if (result.isConfirmed) {
                const url = new URL(window.location.href)
                url.searchParams.set('_switch_user', result.value)
                window.location.href = url.toString()
            }
        })
    })
}

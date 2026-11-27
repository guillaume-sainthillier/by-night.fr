import { defineListener } from '@silarhi/app-js'
import Swal from 'sweetalert2'
import { path } from '@/js/router'
import { create } from '@/js/services/ui/AutocompleteService'

/**
 * Open a user-search dialog and switch to the selected user.
 */
export default defineListener<HTMLElement>({
    selector: '.js-impersonate',
    connect(el) {
        const onClick = async (e: MouseEvent) => {
            e.preventDefault()

            let autocomplete: ReturnType<typeof create> | null = null

            const result = await Swal.fire<string>({
                title: 'Impersonation',
                input: 'text',
                inputLabel: 'Rechercher un utilisateur',
                inputPlaceholder: "Nom d'utilisateur ou e-mail…",
                showCancelButton: true,
                cancelButtonText: 'Annuler',
                confirmButtonText: 'Se connecter en tant que',
                heightAuto: false,
                didOpen() {
                    autocomplete = create({
                        element: '#swal2-input',
                        url: (q) => path('api_users_autocomplete', { _query: { q } }),
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

            // A text input confirms with its value, an empty string at least
            if (result.isConfirmed && result.value !== undefined) {
                const url = new URL(window.location.href)
                url.searchParams.set('_switch_user', result.value)
                window.location.href = url.toString()
            }
        }

        el.addEventListener('click', onClick)

        return () => el.removeEventListener('click', onClick)
    },
})

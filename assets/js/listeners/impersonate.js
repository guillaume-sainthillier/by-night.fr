import Swal from 'sweetalert2'

export default (di, container) => {
    container.querySelectorAll('.js-impersonate').forEach((el) => {
        el.addEventListener('click', async (e) => {
            e.preventDefault()
            const result = await Swal.fire({
                title: 'Impersonation',
                input: 'text',
                inputLabel: 'Username',
                inputPlaceholder: 'Enter the username',
                showCancelButton: true,
                cancelButtonText: 'Annuler',
                heightAuto: false,
                inputValidator: (value) => {
                    if (!value) {
                        return 'Please enter a username'
                    }
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

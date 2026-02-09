import $ from 'jquery'
import Swal from 'sweetalert2'

export default (di, container) => {
    $('.btn-content-removal-request', container).click(function () {
        const btn = $(this)
        const eventId = btn.data('event-id')
        const eventUrl = btn.data('event-url')

        Swal.fire({
            title: 'Signaler un contenu',
            html: `
                <form id="content-removal-form" class="text-start">
                    <div class="mb-3">
                        <label for="removal-email" class="form-label">Votre email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="removal-email" required placeholder="votre@email.com">
                    </div>
                    <div class="mb-3">
                        <label for="removal-type" class="form-label">Type de contenu à supprimer <span class="text-danger">*</span></label>
                        <select class="form-select" id="removal-type" required>
                            <option value="">-- Sélectionnez --</option>
                            <option value="image">Image de couverture</option>
                            <option value="event">Événement complet</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="removal-message" class="form-label">Motif de la demande <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="removal-message" rows="4" required placeholder="Expliquez la raison de votre demande (min. 10 caractères)"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="removal-urls" class="form-label">URLs de l'événement (optionnel)</label>
                        <textarea class="form-control" id="removal-urls" rows="2" placeholder="Une URL par ligne (optionnel)"></textarea>
                        <div class="form-text">Si cet événement apparaît sur d'autres sites, indiquez les URLs ici.</div>
                    </div>
                </form>
            `,
            showCancelButton: true,
            confirmButtonText: 'Envoyer',
            cancelButtonText: 'Annuler',
            focusConfirm: false,
            heightAuto: false,
            customClass: {
                popup: 'swal-wide',
            },
            preConfirm: () => {
                const email = document.getElementById('removal-email').value.trim()
                const type = document.getElementById('removal-type').value
                const message = document.getElementById('removal-message').value.trim()
                const urlsText = document.getElementById('removal-urls').value.trim()

                if (!email) {
                    Swal.showValidationMessage('Veuillez entrer votre adresse email')
                    return false
                }

                if (!type) {
                    Swal.showValidationMessage('Veuillez sélectionner le type de contenu')
                    return false
                }

                if (!message || message.length < 10) {
                    Swal.showValidationMessage('Votre message doit faire au moins 10 caractères')
                    return false
                }

                const eventUrls = urlsText
                    ? urlsText
                          .split('\n')
                          .map((url) => url.trim())
                          .filter((url) => url.length > 0)
                    : []

                return { email, type, message, eventUrls }
            },
        }).then((result) => {
            if (result.isConfirmed) {
                const data = result.value
                data.eventUrls = data.eventUrls || []
                if (eventUrl) {
                    data.eventUrls.unshift(eventUrl)
                }

                Swal.fire({
                    title: 'Envoi en cours...',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading()
                    },
                })

                $.ajax({
                    url: `/api/events/${eventId}/removal-request`,
                    type: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify(data),
                })
                    .done(function (response) {
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Demande envoyée',
                                text: response.message,
                                heightAuto: false,
                            })
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Erreur',
                                text: response.message || "Une erreur s'est produite",
                                heightAuto: false,
                            })
                        }
                    })
                    .fail(function (xhr) {
                        let errorMessage = "Une erreur s'est produite"
                        if (xhr.responseJSON && xhr.responseJSON.violations) {
                            errorMessage = xhr.responseJSON.violations.map((v) => v.message).join('\n')
                        } else if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMessage = xhr.responseJSON.message
                        }
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur',
                            text: errorMessage,
                            heightAuto: false,
                        })
                    })
            }
        })
    })
}

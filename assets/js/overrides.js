import $ from 'jquery'
import Modal from 'bootstrap/js/dist/modal'

Modal.prototype.loading = function () {
    this.setTitle('By Night')
    this.setBody('<h3 class="text-center"><i class="fa fa-spinner text-primary fa-spin fa-3x"></i></h3>')
    this.hideButtons()
}

Modal.prototype.hideButtons = function (selecteur) {
    const element = $(this._element)
    element.find(`.modal-footer :not(${selecteur || '.btn_retour'})`).addClass('hidden')
}
Modal.prototype.setTitle = function (titre) {
    const element = $(this._element)
    element.find('.modal-title').html(titre)
}
Modal.prototype.setBody = function (body) {
    const element = $(this._element)
    element.find('.modal-body').html(body)
}
Modal.prototype.getBody = function () {
    const element = $(this._element)
    return element.find('.modal-body')
}
Modal.prototype.setErreur = function (msg) {
    this.setTitle('Une erreur est survenue')
    this.setBody(msg)
    this.hideButtons()
}

Modal.prototype.setLittleErreur = function (msg) {
    const element = $(this._element)

    element.find('.alert_little').remove()

    const flashMessage = $('<div class="alert alert-danger"><i class="fa fa-warning"></i> </div>').append(msg).hide()
    element.find('.modal-body').prepend(flashMessage)
    flashMessage.slideDown('normal')
}

$.ajaxSetup({
    error: (error, textStatus) => {
        if (textStatus === 404 || textStatus === 500) {
            let message = error.statusText
            try {
                const erreurs = JSON.parse(error.responseText)

                message = ''
                $.each(erreurs, function (k, erreur) {
                    message = `${erreur.message}<br />`
                })
            } catch (e) {
                /* eslint no-unused-vars: "off" */
            }

            const dialog = $('#dialog_details')
            dialog.modal('setErreur', message).modal('show')
        }
    },
})

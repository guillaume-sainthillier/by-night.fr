import $ from 'jquery'
import {Modal} from 'bootstrap'
import Loader2Icon from '@/js/icons/lucide/Loader2'
import TriangleAlertIcon from '@/js/icons/lucide/TriangleAlert'
import { iconHtml } from '@/js/components/icons'

Modal.prototype.loading = function () {
    this.setTitle('By Night')
    this.setBody(`<h3 class="text-center">${iconHtml(Loader2Icon, 'text-primary icon-spin icon-3x')}</h3>`)
    this.hideButtons()
}

Modal.prototype.hideButtons = function (selector) {
    const element = $(this._element)
    element.find(`.modal-footer :not(${selector || '.btn_back'})`).addClass('hidden')
}
Modal.prototype.setTitle = function (title) {
    const element = $(this._element)
    element.find('.modal-title').html(title)
}
Modal.prototype.setBody = function (body) {
    const element = $(this._element)
    element.find('.modal-body').html(body)
}
Modal.prototype.getBody = function () {
    const element = $(this._element)
    return element.find('.modal-body')
}
Modal.prototype.setError = function (msg) {
    this.setTitle('Une erreur est survenue')
    this.setBody(msg)
    this.hideButtons()
}

Modal.prototype.setSmallError = function (msg) {
    const element = $(this._element)

    element.find('.alert_little').remove()

    const flashMessage = $(`<div class="alert alert-danger">${iconHtml(TriangleAlertIcon)} </div>`).append(msg).hide()
    element.find('.modal-body').prepend(flashMessage)
    flashMessage.slideDown('normal')
}

$.ajaxSetup({
    error: (error, textStatus) => {
        if (textStatus === 404 || textStatus === 500) {
            let message = error.statusText
            try {
                const errors = JSON.parse(error.responseText)

                message = ''
                $.each(errors, function (k, err) {
                    message = `${err.message}<br />`
                })
            } catch (e) {
                /* eslint no-unused-vars: "off" */
            }

            const dialog = $('#dialog_details')
            dialog.modal('setError', message).modal('show')
        }
    },
})

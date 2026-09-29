import { Modal } from '@tabler/core'
import $ from 'jquery'
import { iconHtml } from '@/js/components/icons'
import Loader2Icon from '@/js/icons/lucide/Loader2'

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

import { renderIcon } from '@silarhi/ux-icons-js'
import { Modal } from '@tabler/core'
import { LucideLoader2Icon } from '@/js/icons'

/**
 * Helpers added to Bootstrap's Modal for #dialog_details.
 */
declare module '@tabler/core' {
    interface Modal {
        loading(): void
        hideButtons(selector?: string): void
        setTitle(title: string): void
        setBody(body: string): void
        getBody(): HTMLElement | null
        setError(msg: string): void
    }
}

Modal.prototype.loading = function () {
    this.setTitle('By Night')
    this.setBody(
        `<h3 class="text-center">${renderIcon(LucideLoader2Icon, { class: 'icon text-primary icon-spin icon-3x' })}</h3>`
    )
    this.hideButtons()
}

Modal.prototype.hideButtons = function (selector) {
    for (const button of this._element.querySelectorAll(`.modal-footer :not(${selector || '.btn_back'})`)) {
        button.classList.add('hidden')
    }
}
Modal.prototype.setTitle = function (title) {
    const element = this._element.querySelector('.modal-title')
    if (element) {
        element.innerHTML = title
    }
}
Modal.prototype.setBody = function (body) {
    const element = this.getBody()
    if (element) {
        element.innerHTML = body
    }
}
Modal.prototype.getBody = function () {
    return this._element.querySelector<HTMLElement>('.modal-body')
}
Modal.prototype.setError = function (msg) {
    this.setTitle('Une erreur est survenue')
    this.setBody(msg)
    this.hideButtons()
}

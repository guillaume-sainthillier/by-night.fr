import { postForm, statusOf } from '@silarhi/app-js/http'
import { Modal } from '@tabler/core'
import { loadErrorMessage, loadIntoDialog } from '@/js/utils/dialog'
import { popup } from '@/js/utils/utils'

/**
 * What templates/security/connect-success.html.twig sends back from the login popup, as a `social.connected` event on
 * the body of the page that opened it
 */
interface SocialConnected {
    user: { username: string }
    target: Element
}

declare global {
    interface HTMLElementEventMap {
        'social.connected': CustomEvent<SocialConnected>
    }
}

export default class SocialLogin {
    init() {
        // The switch only moves once the network is connected or disconnected: until then it goes back where it was
        for (const checkbox of document.querySelectorAll<HTMLInputElement>('.config-block input[type="checkbox"]')) {
            checkbox.addEventListener('change', () => {
                checkbox.checked = !checkbox.checked
                if (checkbox.checked) {
                    this.launchSocialDisconnect(checkbox)
                } else {
                    this.launchSocialConnect(checkbox)
                }
            })
        }

        document.body.addEventListener('social.connected', ({ detail: { target, user } }) => {
            if (target instanceof HTMLInputElement) {
                this.showAccount(target, true, user.username)
            }
        })
    }

    launchSocialConnect(checkbox: HTMLInputElement) {
        popup(checkbox.dataset.hrefConnect ?? '', checkbox)
    }

    launchSocialDisconnect(checkbox: HTMLInputElement) {
        const dialogEl = document.getElementById('dialog_details')
        if (!dialogEl) {
            return
        }
        const dialog = Modal.getOrCreateInstance(dialogEl)
        if (!(dialog instanceof Modal)) {
            return
        }
        dialog.loading()
        dialog.show()

        loadIntoDialog(dialogEl, checkbox.dataset.hrefDisconnect ?? '', () => {
            for (const confirm of dialogEl.querySelectorAll<HTMLInputElement>('.modal-body input[type="checkbox"]')) {
                this.initModalCheckbox(confirm)
            }
            dialogEl.querySelector('form')?.addEventListener('submit', async (event) => {
                event.preventDefault()
                const form = event.currentTarget
                dialog.loading()
                try {
                    await postForm(form instanceof HTMLFormElement ? form.action : '')
                } catch (error) {
                    dialog.setError(loadErrorMessage(statusOf(error)))
                    return
                }
                dialog.hide()
                this.showAccount(checkbox, false, '')
            })
        })
    }

    showAccount(checkbox: HTMLInputElement, connected: boolean, username: string) {
        checkbox.checked = connected
        const account = checkbox.closest('.config-block')?.querySelector('.username')
        if (account) {
            account.textContent = username
        }
    }

    initModalCheckbox(checkbox: HTMLInputElement) {
        checkbox.addEventListener('click', () => {
            checkbox.closest('.modal-body')?.querySelector('.alert')?.classList.toggle('hidden', !checkbox.checked)
        })
    }
}

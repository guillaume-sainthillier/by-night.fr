import $ from 'jquery'
import { loadIntoDialog } from '@/js/utils/dialog'
import { popup } from '@/js/utils/utils'

/**
 * What templates/security/connect-success.html.twig sends back from the login popup
 */
interface Connected {
    user: { username: string }
    target: Element
}

export default class SocialLogin {
    init() {
        this.initOnOff()

        // Default actions. jQuery passes the checkbox triggered along as its element (trigger() unwraps a collection)
        $('body')
            .on('wantConnect', (_event, checkbox: HTMLElement) => {
                this.launchSocialConnect(checkbox)
            })
            .on('wantDisconnect', (_event, checkbox: HTMLElement) => {
                this.launchSocialDisconnect(checkbox)
            })
            .on('hasDisconnected', (_event, checkbox: HTMLElement) => {
                this.onDisconnectedSocial(checkbox)
            })
            .on('hasConnected', (_event, ui: Connected) => {
                const checkbox = ui.target
                const { user } = ui

                const configBlock = $(checkbox).closest('.config-block')

                $(checkbox).prop('checked', true)
                configBlock.find('.username').text(user.username)
            })
    }

    initOnOff() {
        $('.config-block input:checkbox').each((_index, element) => {
            $(element)
                .off('change')
                .change((event) => {
                    const checkbox = $(event.currentTarget)
                    $(checkbox).prop('checked', !$(checkbox).prop('checked'))
                    if ($(checkbox).prop('checked')) {
                        // Disconnect
                        $('body').trigger('wantDisconnect', checkbox)
                    } // Connect
                    else {
                        $('body').trigger('wantConnect', checkbox)
                    }
                })
        })
    }

    // Deps: ['app/App']
    launchSocialConnect(checkbox: HTMLElement) {
        popup($(checkbox).data('href-connect'), checkbox)
    }

    launchSocialDisconnect(checkbox: HTMLElement) {
        const dialog = $('#dialog_details').modal('loading').modal('show')

        loadIntoDialog(dialog, $(checkbox).data('href-disconnect'), () => {
            this.initModalCheckbox(dialog.modal('getBody').find('input:checkbox'))
            dialog
                .find('form')
                .off('submit')
                .submit((event) => {
                    dialog.modal('loading')
                    $.post($(event.currentTarget).attr('action') ?? '').done(() => {
                        dialog.modal('hide')
                        $('body').trigger('hasDisconnected', $(checkbox))
                    })
                    return false
                })
        })
    }

    onDisconnectedSocial(checkbox: HTMLElement) {
        const configBlock = $(checkbox).closest('.config-block')

        $(checkbox).prop('checked', false)
        configBlock.find('.username').text('')
    }

    initModalCheckbox(checkbox: JQuery) {
        $(checkbox)
            .off('click')
            .click((event) => {
                const alert = $(event.currentTarget).closest('.modal-body').find('.alert')
                if ($(event.currentTarget).prop('checked')) {
                    alert.removeClass('hidden')
                } else {
                    alert.addClass('hidden')
                }
            })
    }
}

import $ from 'jquery'
import { popup } from '@/js/utils/utils'

export default class SocialLogin {
    init() {
        const self = this
        self.initOnOff()

        // Default actions
        $('body')
            .on('wantConnect', function (event, checkbox) {
                self.launchSocialConnect(checkbox)
            })
            .on('wantDisconnect', function (event, checkbox) {
                self.launchSocialDisconnect(checkbox)
            })
            .on('hasDisconnected', function (event, checkbox) {
                self.onDisconnectedSocial(checkbox)
            })
            .on('hasConnected', function (event, ui) {
                const checkbox = ui.target
                const { user } = ui

                const configBlock = $(checkbox).closest('.config-block')

                $(checkbox).prop('checked', true)
                configBlock.find('.username').text(user.username)
            })
    }

    initOnOff() {
        $('.config-block input:checkbox').each(function () {
            $(this)
                .off('change')
                .change(function () {
                    const checkbox = $(this)
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
    launchSocialConnect(checkbox) {
        popup($(checkbox).data('href-connect'), checkbox)
    }

    launchSocialDisconnect(checkbox) {
        const self = this
        const dialog = $('#dialog_details').modal('loading').modal('show')

        dialog.load($(checkbox).data('href-disconnect'), function () {
            self.initModalCheckbox(dialog.modal('getBody').find('input:checkbox'))
            dialog
                .find('form')
                .off('submit')
                .submit(function () {
                    dialog.modal('loading')
                    $.post($(this).attr('action')).done(function () {
                        dialog.modal('hide')
                        $('body').trigger('hasDisconnected', $(checkbox))
                    })
                    return false
                })
        })
    }

    onDisconnectedSocial(checkbox) {
        const configBlock = $(checkbox).closest('.config-block')

        $(checkbox).prop('checked', false)
        configBlock.find('.username').text('')
    }

    /**
     *
     * @param {jQuery} checkbox
     * @returns {undefined}
     */
    initModalCheckbox(checkbox) {
        $(checkbox)
            .off('click')
            .click(function () {
                const alert = $(this).closest('.modal-body').find('.alert')
                if ($(this).prop('checked')) {
                    alert.removeClass('hidden')
                } else {
                    alert.addClass('hidden')
                }
            })
    }
}

import $ from 'jquery'
import { popup } from '@/js/utils/utils'

export default class SocialLogin {
    init() {
        const self = this
        self.initOnOff()

        // Actions par défaut
        $('body')
            .on('wantConnect', function (event, ck) {
                self.launchSocialConnect(ck)
            })
            .on('wantDisconnect', function (event, ck) {
                self.launchSocialDisconnect(ck)
            })
            .on('hasDisconnected', function (event, ck) {
                self.onDisconnectedSocial(ck)
            })
            .on('hasConnected', function (event, ui) {
                const ck = ui.target
                const { user } = ui

                const configBlock = $(ck).closest('.bloc_config')

                $(ck).prop('checked', true)
                configBlock.find('.username').text(user.username)
            })
    }

    initOnOff() {
        $('.bloc_config input:checkbox').each(function () {
            $(this)
                .off('change')
                .change(function () {
                    const ck = $(this)
                    $(ck).prop('checked', !$(ck).prop('checked'))
                    if ($(ck).prop('checked')) {
                        // Déconnexion
                        $('body').trigger('wantDisconnect', ck)
                    } // Connexion
                    else {
                        $('body').trigger('wantConnect', ck)
                    }
                })
        })
    }

    // Deps: ['app/App']
    launchSocialConnect(ck) {
        popup($(ck).data('href-connect'), ck)
    }

    launchSocialDisconnect(ck) {
        const self = this
        const dialog = $('#dialog_details').modal('loading').modal('show')

        dialog.load($(ck).data('href-disconnect'), function () {
            self.initModalCheckbox(dialog.modal('getBody').find('input:checkbox'))
            dialog
                .find('form')
                .off('submit')
                .submit(function () {
                    dialog.modal('loading')
                    $.post($(this).attr('action')).done(function () {
                        dialog.modal('hide')
                        $('body').trigger('hasDisconnected', $(ck))
                    })
                    return false
                })
        })
    }

    onDisconnectedSocial(ck) {
        const configBlock = $(ck).closest('.bloc_config')

        $(ck).prop('checked', false)
        configBlock.find('.username').text('')
    }

    /**
     *
     * @param {jQuery} ck
     * @returns {undefined}
     */
    initModalCheckbox(ck) {
        $(ck)
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

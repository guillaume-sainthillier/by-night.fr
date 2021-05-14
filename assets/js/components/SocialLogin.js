import { popup } from '../utils/utils';

export default class SocialLogin {
    init() {
        const self = this;
        $(function () {
            self.initOnOff();

            //Actions par défaut
            $('body')
                .on('wantConnect', function (event, ck) {
                    self.launchSocialConnect(ck);
                })
                .on('wantDisconnect', function (event, ck) {
                    self.launchSocialDisconnect(ck);
                })
                .on('hasDisconnected', function (event, ck) {
                    self.onDisconnectedSocial(ck);
                })
                .on('hasConnected', function (event, ui) {
                    const ck = ui.target;
                    const user = ui.user;

                    const bloc_config = $(ck).closest('.bloc_config');

                    $(ck).prop('checked', true);
                    bloc_config.find('.username').text(user.username);
                    bloc_config.find('.when_on').show('normal', function () {
                        $(this).removeClass('hidden');
                    });
                });
        });
    }

    initOnOff() {
        $('.bloc_config input:checkbox').each(function () {
            $(this)
                .off('change')
                .change(function () {
                    const ck = $(this);
                    $(ck).prop('checked', !$(ck).prop('checked'));
                    if ($(ck).prop('checked')) {
                        //Déconnexion
                        $('body').trigger('wantDisconnect', ck);
                    } //Connexion
                    else {
                        $('body').trigger('wantConnect', ck);
                    }
                });
        });
    }

    //Deps: ['app/App']
    launchSocialConnect(ck) {
        popup($(ck).data('href-connect'), ck);
    }

    launchSocialDisconnect(ck) {
        const self = this;
        const dialog = $('#dialog_details').modal('loading').modal('show');

        dialog.load($(ck).data('href-disconnect'), function () {
            self.initModalCheckbox(dialog.modal('getBody').find('input:checkbox'));
            dialog
                .find('form')
                .off('submit')
                .submit(function () {
                    dialog.modal('loading');
                    $.post($(this).attr('action')).done(function () {
                        dialog.modal('hide');
                        $('body').trigger('hasDisconnected', $(ck));
                    });
                    return false;
                });
        });
    }

    onDisconnectedSocial(ck) {
        var bloc_config = $(ck).closest('.bloc_config');

        $(ck).prop('checked', false);
        bloc_config.find('.when_on').hide('normal', function () {
            $(this).addClass('hidden');
        });
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
                var div_alert = $(this).closest('.modal-body').find('.alert');
                if ($(this).prop('checked')) {
                    div_alert.removeClass('hidden');
                } else {
                    div_alert.addClass('hidden');
                }
            });
    }
}

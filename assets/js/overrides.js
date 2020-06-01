$.fn.extend($.fn.modal.Constructor.prototype, {
    loading: function () {
        this.setTitle('By Night');
        this.setBody('<h3 class="text-center"><i class="fa fa-spinner text-primary fa-spin fa-3x"></i></h3>');
        this.hideButtons();
    },
    hideButtons: function (selecteur) {
        var element = $(this._element);
        element.find('.modal-footer :not(' + (selecteur || '.btn_retour') + ')').addClass('hidden');
    },
    setTitle: function (titre) {
        var element = $(this._element);
        element.find('.modal-title').html(titre);
    },
    setBody: function (body) {
        var element = $(this._element);
        element.find('.modal-body').html(body);
    },
    getBody: function () {
        var element = $(this._element);
        return element.find('.modal-body');
    },
    setErreur: function (msg) {
        this.setTitle('Une erreur est survenue');
        this.setBody(msg);
        this.hideButtons();
    },
    setLittleErreur: function (msg) {
        var element = $(this._element);

        element.find('.alert_little').remove();

        var flash_msg = $('<div class="alert alert-danger alert_little"><i class="fa fa-warning"></i> </div>')
            .append(msg)
            .hide();
        element.find('.modal-body').prepend(flash_msg);
        flash_msg.slideDown('normal');
    },
});

$.ajaxSetup({
    error: function (error, textStatus, errorThrown) {
        if (textStatus === 404 || textStatus === 500) {
            try {
                var message = error.statusText;
                var erreurs = JSON.parse(error.responseText);

                message = '';
                $.each(erreurs, function (k, erreur) {
                    message = erreur.message + '<br />';
                });
            } catch (e) {}

            var dialog = $('#dialog_details');
            dialog.modal('setErreur', message).modal('show');
        }
    },
});

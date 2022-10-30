$(document).ready(function () {
    $('.form-delete').submit(function () {
        return window.confirm(
            "Cette action va supprimer l'événement ainsi que toutes les données rattachées. Continuer ?"
        );
    });

    $('.draft').change(function () {
        const self = $(this);

        self.attr('disabled', true);
        $.post(self.data('href'), {
            draft: !self.prop('checked'),
        }).done(function () {
            self.attr('disabled', false);
        });
    });

    $('.cancel').change(function () {
        const self = $(this);
        self.attr('disabled', true);
        $.post(self.data('href'), {
            cancel: self.prop('checked'),
        }).done(function () {
            self.attr('disabled', false);
        });
    });
});

export default class UserEventsList {
    init() {
        $(function () {
            $('.form-delete').submit(function () {
                return confirm(
                    "Cette action va supprimer l'événement ainsi que toutes les données rattachées. Continuer ?"
                );
            });

            $('.brouillon').change(function () {
                var self = $(this);

                self.attr('disabled', true);
                $.post(self.data('href'), {
                    brouillon: !self.prop('checked'),
                }).done(function () {
                    self.attr('disabled', false);
                });
            });

            $('.annuler').change(function () {
                var self = $(this);
                self.attr('disabled', true);
                $.post(self.data('href'), {
                    annuler: self.prop('checked'),
                }).done(function () {
                    self.attr('disabled', false);
                });
            });
        });
    }
}

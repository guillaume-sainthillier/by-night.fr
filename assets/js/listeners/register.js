export default (di, container) => {
    const handleRegister = function ($dialog) {
        App.dispatchPageLoadedEvent($dialog[0]); //$dialog is a jQuery object so we pass the pure dom object
        $dialog
            .find('form')
            .off('submit')
            .submit(function () {
                var href = $(this).attr('action');
                var datas = $(this).serialize();
                var submit_button = $('#_register');
                submit_button.button('loading');
                $.post(href, datas).done(function (data) {
                    submit_button.button('reset');

                    if (typeof data.success === 'boolean' && data.success) {
                        $dialog.modal('hide');
                        location.reload();
                    } else {
                        $dialog.html(data);
                        handleRegister($dialog); //ne rien mettre après
                    }
                });
                return false;
            });
    };

    $('.register', container)
        .off('click')
        .click(function (e) {
            e.preventDefault();

            var $dialog = $('#dialog_details');
            $dialog
                .modal('show')
                .modal('loading')
                .load($(this).attr('href'), function () {
                    handleRegister($dialog);
                });
        });
};

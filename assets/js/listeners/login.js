import $ from 'jquery'

export default (di, container) => {
    const handleLogin = function ($dialog) {
        window.App.dispatchPageLoadedEvent($dialog[0]) // $dialog is a jQuery object so we pass the pure dom object
        $dialog
            .find('form')
            .off('submit')
            .submit(function () {
                const href = $(this).attr('action')
                const datas = $(this).serialize()
                const submitButton = $('#_submit')
                submitButton.button('loading')
                $
                    .post(href, datas)
                    .done(function (data) {
                    submitButton.button('reset')

                    if (!data.success) {
                        $dialog.modal('setLittleErreur', data.message)
                    } else {
                        $dialog.modal('hide')
                        window.location.reload()
                    }
                })
                    .fail(function (jqXHR) {
                        if(jqXHR.status === 422) {
                            $dialog.html(jqXHR.responseText)
                            handleLogin($dialog) // ne rien mettre après
                        }
                    })
                return false
            })
    }

    $('.connexion', container)
        .off('click')
        .click(function (e) {
            e.preventDefault()

            const $dialog = $('#dialog_details')
            $dialog
                .modal('show')
                .modal('loading')
                .load($(this).attr('href'), function () {
                    handleLogin($dialog)
                })
        })
}

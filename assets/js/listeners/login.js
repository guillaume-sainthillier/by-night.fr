import $ from 'jquery'

/**
 * Open the login dialog and handle its AJAX form submission.
 */
export default {
    selector: '.login',
    connect(element, { app }) {
        const handleLogin = ($dialog) => {
            app.mount($dialog[0]) // $dialog is a jQuery object so we pass the pure dom object
            $dialog
                .find('form')
                .off('submit')
                .submit(function () {
                    const href = $(this).attr('action')
                    const datas = $(this).serialize()
                    const submitButton = $('#_submit')
                    submitButton.button('loading')
                    $.post(href, datas)
                        .done((data) => {
                            submitButton.button('reset')

                            if (!data.success) {
                                $dialog.modal('setSmallError', data.message)
                            } else {
                                $dialog.modal('hide')
                                window.location.reload()
                            }
                        })
                        .fail((jqXHR) => {
                            if (jqXHR.status === 422) {
                                $dialog.html(jqXHR.responseText)
                                handleLogin($dialog) // don't add anything after this
                            }
                        })
                    return false
                })
        }

        const $element = $(element)

        $element.on('click.login', function (e) {
            e.preventDefault()

            const $dialog = $('#dialog_details')
            $dialog
                .modal('show')
                .modal('loading')
                .load($(this).attr('href'), () => {
                    handleLogin($dialog)
                })
        })

        return () => $element.off('.login')
    },
}

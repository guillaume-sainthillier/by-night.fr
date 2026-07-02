import $ from 'jquery'

/**
 * Open the register dialog and handle its AJAX form submission.
 */
export default {
    selector: '.register',
    connect(element, { app }) {
        const handleRegister = ($dialog) => {
            app.mount($dialog[0]) // $dialog is a jQuery object so we pass the pure dom object
            $dialog
                .find('form')
                .off('submit')
                .submit(function () {
                    const href = $(this).attr('action')
                    const datas = $(this).serialize()
                    const submitButton = $('#_register')
                    submitButton.button('loading')
                    $.post(href, datas)
                        .done((data) => {
                            submitButton.button('reset')

                            if (typeof data.success === 'boolean' && data.success) {
                                $dialog.modal('hide')
                                window.location.reload()
                            } else {
                                $dialog.html(data)
                                handleRegister($dialog) // ne rien mettre après
                            }
                        })
                        .fail((jqXHR) => {
                            if (jqXHR.status === 422) {
                                $dialog.html(jqXHR.responseText)
                                handleRegister($dialog) // ne rien mettre après
                            }
                        })
                    return false
                })
        }

        const $element = $(element)

        $element.on('click.register', function (e) {
            e.preventDefault()

            const $dialog = $('#dialog_details')
            $dialog
                .modal('show')
                .modal('loading')
                .load($(this).attr('href'), () => {
                    handleRegister($dialog)
                })
        })

        return () => $element.off('.register')
    },
}

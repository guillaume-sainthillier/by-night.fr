import $ from 'jquery'

export default (di, container) => {
    $('a.email-verify, button.email-verify', container).click(function (e) {
        e.preventDefault()
        const url = $(this).attr('href') || $(this).data('href')
        $.post(url).done(() => {
            di.get('toastManager').createToast('success', 'Un email de vérification a bien été envoyé.')
            if ($(this).closest('.alert').length > 0) {
                $(this).closest('.alert').alert('close')
            } else {
                $(this).remove()
            }
        })
    })
}

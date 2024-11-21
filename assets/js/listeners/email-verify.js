import $ from 'jquery'

export default (di, container) => {
    $('a.email-verify, button.email-verify', container).click(function (e) {
        const that = this
        e.preventDefault()
        const url = $(this).attr('href') || $(this).data('href')
        $.post(url).done(() => {
            di.get('toastManager').createToast('success', 'Un email de vérification a bien été envoyé.')
            if ($(that).closest('.alert').length > 0) {
                $(that).closest('.alert').alert('close')
            } else {
                $(that).remove()
            }
        })
    })
}

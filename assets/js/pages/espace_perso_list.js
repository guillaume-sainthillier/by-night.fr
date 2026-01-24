import $ from 'jquery'

$(document).ready(function () {
    $('.form-delete').submit(function () {
        return window.confirm(
            "Cette action va supprimer l'événement ainsi que toutes les données rattachées. Continuer ?"
        )
    })

    $('.draft').change(function () {
        const self = $(this)

        self.attr('disabled', true)
        $.ajax({
            url: self.data('href'),
            type: 'PUT',
            contentType: 'application/json',
            data: JSON.stringify({ draft: !self.prop('checked') }),
        }).done(function () {
            self.attr('disabled', false)
        })
    })

    $('.cancel').change(function () {
        const self = $(this)
        self.attr('disabled', true)
        $.ajax({
            url: self.data('href'),
            type: 'PUT',
            contentType: 'application/json',
            data: JSON.stringify({ cancel: self.prop('checked') }),
        }).done(function () {
            self.attr('disabled', false)
        })
    })
})

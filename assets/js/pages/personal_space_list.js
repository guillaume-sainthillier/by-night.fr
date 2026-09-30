import $ from 'jquery'

/** @type {Page} */
function initialize() {
    $('.draft').change(function () {
        const self = $(this)

        self.attr('disabled', true)
        $.ajax({
            url: self.data('href'),
            type: 'PUT',
            contentType: 'application/json',
            data: JSON.stringify({ draft: !self.prop('checked') }),
        }).done(() => {
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
        }).done(() => {
            self.attr('disabled', false)
        })
    })
}

window.App.registerPage('personal_space_list', initialize)

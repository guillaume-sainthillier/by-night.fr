import type { Page } from '@silarhi/app-js'
import $ from 'jquery'

const initialize: Page = () => {
    $('.draft').change((event) => {
        const self = $(event.currentTarget)

        self.prop('disabled', true)
        $.ajax({
            url: self.data('href'),
            type: 'PUT',
            contentType: 'application/json',
            data: JSON.stringify({ draft: !self.prop('checked') }),
        }).done(() => {
            self.prop('disabled', false)
        })
    })

    $('.cancel').change((event) => {
        const self = $(event.currentTarget)
        self.prop('disabled', true)
        $.ajax({
            url: self.data('href'),
            type: 'PUT',
            contentType: 'application/json',
            data: JSON.stringify({ cancel: self.prop('checked') }),
        }).done(() => {
            self.prop('disabled', false)
        })
    })
}

window.App.registerPage('personal_space_list', initialize)

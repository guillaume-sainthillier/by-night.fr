import { definePage } from '@silarhi/app-js'
import $ from 'jquery'

export default definePage(() => {
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
})

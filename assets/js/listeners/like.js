import $ from 'jquery'

export default (di, container) => {
    const options = {
        css_selector_like: '.btn-like-event',
        css_active_class: 'btn-primary',
    }

    $(options.css_selector_like, container).click(function () {
        const btn = $(this)

        if (btn.hasClass('login')) {
            return false
        }

        btn.attr('disabled', true)
        $.ajax({
            url: btn.data('href'),
            type: 'PUT',
            contentType: 'application/json',
            data: JSON.stringify({ like: !btn.hasClass(options.css_active_class) }),
        }).done(function (msg) {
            btn.attr('disabled', !msg.success)
            if (msg.success) {
                btn.toggleClass(options.css_active_class, msg.like)
            }
        })
    })
}

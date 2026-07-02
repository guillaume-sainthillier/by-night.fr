import $ from 'jquery'

const options = {
    css_selector_like: '.btn-like-event',
    css_active_class: 'btn-primary',
}

/**
 * Toggle an event like via AJAX when the like button is clicked.
 *
 * @type {Listener}
 */
export default {
    selector: options.css_selector_like,
    connect(element) {
        const $element = $(element)

        $element.on('click.like', function () {
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
            }).done((msg) => {
                btn.attr('disabled', !msg.success)
                if (msg.success) {
                    btn.toggleClass(options.css_active_class, msg.like)
                }
            })
        })

        return () => $element.off('.like')
    },
}

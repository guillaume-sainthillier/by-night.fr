import $ from 'jquery'

const options = {
    css_selector_like: '.btn-like-event',
    css_active_class: 'btn-primary',
}

/**
 * Toggle an event like via AJAX when the like button is clicked.
 *
 * A button with a `data-intent` ("participer") also records the click a visitor made before logging in: the login
 * leads back to the page with that fragment (LoginTargetPath), and the button is clicked once for them, unless the
 * event is already in their outings.
 *
 * @type {Listener}
 */
export default {
    selector: options.css_selector_like,
    connect(element, { app }) {
        const $element = $(element)

        const like = (btn) => {
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
                    if (msg.like) {
                        app.get('toastManager').createToast('success', 'Ajouté à vos sorties')
                    }
                }
            })
        }

        $element.on('click.like', function () {
            like($(this))
        })

        const { intent } = element.dataset
        if (intent && window.location.hash === `#${intent}`) {
            // Once: a reload or a shared URL must not click again
            window.history.replaceState(null, '', window.location.pathname + window.location.search)
            if (!$element.hasClass(options.css_active_class)) {
                like($element)
            }
        }

        return () => $element.off('.like')
    },
}

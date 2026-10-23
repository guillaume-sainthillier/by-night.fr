import $ from 'jquery'
import type { Listener } from '@/js/types'

/**
 * Send a verification email when the element is clicked, then dismiss its
 * alert (or remove the element).
 */
export default {
    selector: 'a.email-verify, button.email-verify',
    connect(element, { app }) {
        const $element = $(element)

        $element.on('click.emailVerify', function (this: HTMLElement, e) {
            e.preventDefault()
            const url = $(this).attr('href') || $(this).data('href')
            $.post(url).done(() => {
                app.get('toastManager').createToast('success', 'Un e-mail de vérification a bien été envoyé.')
                if ($(this).closest('.alert').length > 0) {
                    $(this).closest('.alert').alert('close')
                } else {
                    $(this).remove()
                }
            })
        })

        return () => $element.off('.emailVerify')
    },
} satisfies Listener<HTMLAnchorElement | HTMLButtonElement>

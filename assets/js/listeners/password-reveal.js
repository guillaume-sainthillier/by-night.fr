/**
 * Show or hide what was typed in a password field (components/PasswordInput.html.twig).
 *
 * @type {Listener}
 */
export default {
    selector: '[data-password-reveal]',
    connect(button) {
        const input = button.closest('.input-group')?.querySelector('input')
        if (!input) {
            return
        }

        const show = (shown) => {
            input.type = shown ? 'text' : 'password'
            button.setAttribute('aria-pressed', String(shown))
            button.title = shown ? 'Masquer le mot de passe' : 'Afficher le mot de passe'
            button.querySelector('[data-password-reveal-icon="hidden"]').classList.toggle('d-none', shown)
            button.querySelector('[data-password-reveal-icon="shown"]').classList.toggle('d-none', !shown)
        }

        const toggle = () => show(input.type === 'password')
        // Hidden again before sending: a text field would let the browser keep the password in its form history
        const hide = () => show(false)

        button.addEventListener('click', toggle)
        input.form?.addEventListener('submit', hide)

        return () => {
            button.removeEventListener('click', toggle)
            input.form?.removeEventListener('submit', hide)
        }
    },
}

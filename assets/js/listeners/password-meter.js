/**
 * The rules a password meets, in order. The patterns are PasswordRequirements::RULES, read with the flags the server
 * uses ("s" and "u"), so the meter never checks a rule the server would judge otherwise.
 *
 * @param {string} password
 * @param {string[]} patterns
 * @returns {boolean[]}
 */
export const checkRules = (password, patterns) => patterns.map((pattern) => new RegExp(pattern, 'su').test(password))

/**
 * The colour of the bar: red until half the rules are met, then orange, green once they all are.
 *
 * @param {number} met
 * @param {number} total
 * @returns {string|null} a Bootstrap background class, null for an empty bar
 */
export const strengthTone = (met, total) => {
    if (met === 0) {
        return null
    }

    if (met === total) {
        return 'bg-success'
    }

    return met * 2 >= total ? 'bg-warning' : 'bg-danger'
}

const TONES = ['bg-danger', 'bg-warning', 'bg-success']

/**
 * Fill the strength meter of a new password (form theme block "new_password_row") as the member types: the bar grows
 * with each rule met and each rule gets its check.
 *
 * @type {Listener}
 */
export default {
    selector: '[data-password-meter]',
    connect(meter) {
        const input = document.getElementById(meter.dataset.passwordMeter)
        if (!input) {
            return
        }

        const progress = meter.querySelector('[role="progressbar"]')
        const bar = progress.querySelector('.progress-bar')
        const rules = [...meter.querySelectorAll('[data-password-meter-rule]')]
        const patterns = rules.map((rule) => rule.dataset.passwordMeterRule)

        const update = () => {
            const results = checkRules(input.value, patterns)
            const met = results.filter(Boolean).length
            const tone = strengthTone(met, rules.length)

            rules.forEach((rule, i) => {
                rule.querySelector('[data-password-meter-rule-icon="met"]').classList.toggle('d-none', !results[i])
                rule.querySelector('[data-password-meter-rule-icon="unmet"]').classList.toggle('d-none', results[i])
            })

            bar.style.width = `${(met / rules.length) * 100}%`
            bar.classList.remove(...TONES)
            if (tone) {
                bar.classList.add(tone)
            }
            progress.setAttribute('aria-valuenow', String(met))
        }

        input.addEventListener('input', update)
        // A browser that restores the field (back button, autofill) fills it without an input event
        update()

        return () => input.removeEventListener('input', update)
    },
}

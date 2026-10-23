const nameCheck = /^[-_a-zA-Z0-9]{4,22}$/
const tokenCheck = /^[-_/+a-zA-Z0-9]{24,}$/

/** What @hotwired/turbo's turbo:submit-start / turbo:submit-end events carry, as far as this module reads it */
interface TurboSubmitDetail {
    formSubmission: {
        formElement: HTMLFormElement
        fetchRequest: { headers: Record<string, string> }
    }
}

declare global {
    interface DocumentEventMap {
        'turbo:submit-start': CustomEvent<TurboSubmitDetail>
        'turbo:submit-end': CustomEvent<TurboSubmitDetail>
    }
}

// Generate and double-submit a CSRF token in a form field and a cookie, as defined by Symfony's SameOriginCsrfTokenManager
// Use `form.requestSubmit()` to ensure that the submit event is triggered. Using `form.submit()` will not trigger the event
// and thus this event-listener will not be executed.
document.addEventListener(
    'submit',
    (event) => {
        if (event.target instanceof HTMLFormElement) {
            generateCsrfToken(event.target)
        }
    },
    true
)

// When @hotwired/turbo handles form submissions, send the CSRF token in a header in addition to a cookie
// The `framework.csrf_protection.check_header` config option needs to be enabled for the header to be checked
document.addEventListener('turbo:submit-start', (event) => {
    const h = generateCsrfHeaders(event.detail.formSubmission.formElement)
    Object.keys(h).map((k) => {
        event.detail.formSubmission.fetchRequest.headers[k] = h[k]
    })
})

// When @hotwired/turbo handles form submissions, remove the CSRF cookie once a form has been submitted
document.addEventListener('turbo:submit-end', (event) => {
    removeCsrfToken(event.detail.formSubmission.formElement)
})

const findCsrfField = (formElement: HTMLFormElement) =>
    formElement.querySelector<HTMLInputElement>('input[data-controller="csrf-protection"], input[name="_csrf_token"]')

export function generateCsrfToken(formElement: HTMLFormElement) {
    const csrfField = findCsrfField(formElement)

    if (!csrfField) {
        return
    }

    let csrfCookie = csrfField.getAttribute('data-csrf-protection-cookie-value')
    let csrfToken = csrfField.value

    if (!csrfCookie && nameCheck.test(csrfToken)) {
        csrfField.setAttribute('data-csrf-protection-cookie-value', (csrfCookie = csrfToken))
        csrfField.defaultValue = csrfToken = btoa(
            String.fromCharCode(...window.crypto.getRandomValues(new Uint8Array(18)))
        )
    }
    csrfField.dispatchEvent(new Event('change', { bubbles: true }))

    if (csrfCookie && tokenCheck.test(csrfToken)) {
        const cookie = `${csrfCookie}_${csrfToken}=${csrfCookie}; path=/; samesite=strict`
        // biome-ignore lint/suspicious/noDocumentCookie: double-submit CSRF cookie must be written synchronously (Cookie Store API is async/unsupported in Firefox)
        document.cookie = window.location.protocol === 'https:' ? `__Host-${cookie}; secure` : cookie
    }
}

export function generateCsrfHeaders(formElement: HTMLFormElement) {
    const headers: Record<string, string> = {}
    const csrfField = findCsrfField(formElement)

    if (!csrfField) {
        return headers
    }

    // RegExp.test() reads a missing attribute (null) as the string "null", a valid cookie name
    const csrfCookie = String(csrfField.getAttribute('data-csrf-protection-cookie-value'))

    if (tokenCheck.test(csrfField.value) && nameCheck.test(csrfCookie)) {
        headers[csrfCookie] = csrfField.value
    }

    return headers
}

export function removeCsrfToken(formElement: HTMLFormElement) {
    const csrfField = findCsrfField(formElement)

    if (!csrfField) {
        return
    }

    // RegExp.test() reads a missing attribute (null) as the string "null", a valid cookie name
    const csrfCookie = String(csrfField.getAttribute('data-csrf-protection-cookie-value'))

    if (tokenCheck.test(csrfField.value) && nameCheck.test(csrfCookie)) {
        const cookie = `${csrfCookie}_${csrfField.value}=0; path=/; samesite=strict; max-age=0`

        // biome-ignore lint/suspicious/noDocumentCookie: double-submit CSRF cookie must be written synchronously (Cookie Store API is async/unsupported in Firefox)
        document.cookie = window.location.protocol === 'https:' ? `__Host-${cookie}; secure` : cookie
    }
}

/* stimulusFetch: 'lazy' */
export default 'csrf-protection-controller'

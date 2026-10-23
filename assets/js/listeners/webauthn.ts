import type { Listener } from '@/js/types'

/**
 * The passkey ceremonies of `form.js-webauthn`: on submit, ask the browser for a passkey with the options of
 * `data-webauthn-options` (JSON from App\Security\TwoFactor\Webauthn), write its answer as JSON in the form's
 * `[data-webauthn-response]` field, then submit the form.
 *
 * - `data-webauthn-action="register"`: a new passkey (navigator.credentials.create)
 * - `data-webauthn-action="authenticate"`: the second factor of a login (navigator.credentials.get)
 *
 * A failure is shown in the form's `[data-webauthn-error]` alert.
 */

/** A credential descriptor as the server sends it: its id in base64url */
type CredentialDescriptorJson = Omit<PublicKeyCredentialDescriptor, 'id'> & { id: string }

/** The options of a registration as the server sends them: every binary value in base64url */
type CreationOptionsJson = Omit<PublicKeyCredentialCreationOptions, 'challenge' | 'user' | 'excludeCredentials'> & {
    challenge: string
    user: Omit<PublicKeyCredentialUserEntity, 'id'> & { id: string }
    excludeCredentials?: CredentialDescriptorJson[]
}

/** The options of a login as the server sends them */
type RequestOptionsJson = Omit<PublicKeyCredentialRequestOptions, 'challenge' | 'allowCredentials'> & {
    challenge: string
    allowCredentials?: CredentialDescriptorJson[]
}

// WebAuthn sends every binary value in base64url (RFC 4648 §5); the browser API wants and returns ArrayBuffers
const fromBase64Url = (value: string): Uint8Array<ArrayBuffer> => {
    const base64 = value.replace(/-/g, '+').replace(/_/g, '/')
    return Uint8Array.from(atob(base64.padEnd(Math.ceil(base64.length / 4) * 4, '=')), (c) => c.charCodeAt(0))
}

const toBase64Url = (buffer: ArrayBuffer): string =>
    btoa(String.fromCharCode(...new Uint8Array(buffer)))
        .replace(/\+/g, '-')
        .replace(/\//g, '_')
        .replace(/=+$/, '')

const decodeCredentials = (credentials?: CredentialDescriptorJson[]): PublicKeyCredentialDescriptor[] | undefined =>
    credentials?.map((credential) => ({ ...credential, id: fromBase64Url(credential.id) }))

const decodeCreationOptions = (options: CreationOptionsJson): PublicKeyCredentialCreationOptions => ({
    ...options,
    challenge: fromBase64Url(options.challenge),
    user: { ...options.user, id: fromBase64Url(options.user.id) },
    excludeCredentials: decodeCredentials(options.excludeCredentials),
})

const decodeRequestOptions = (options: RequestOptionsJson): PublicKeyCredentialRequestOptions => ({
    ...options,
    challenge: fromBase64Url(options.challenge),
    allowCredentials: decodeCredentials(options.allowCredentials),
})

const encodeCredential = (credential: PublicKeyCredential) => {
    const response = credential.response as AuthenticatorResponse &
        Partial<AuthenticatorAttestationResponse> &
        Partial<Omit<AuthenticatorAssertionResponse, 'userHandle'>> & { userHandle?: ArrayBuffer | null }
    const encoded: { id: string; type: string; rawId: string; response: Record<string, unknown> } = {
        id: credential.id,
        type: credential.type,
        rawId: toBase64Url(credential.rawId),
        response: { clientDataJSON: toBase64Url(response.clientDataJSON) },
    }

    for (const name of ['attestationObject', 'authenticatorData', 'signature', 'userHandle'] as const) {
        const value = response[name]
        if (value) {
            encoded.response[name] = toBase64Url(value)
        }
    }
    if (typeof response.getTransports === 'function') {
        encoded.response.transports = response.getTransports()
    }

    return encoded
}

const MESSAGES: Record<string, string> = {
    unsupported: 'Votre navigateur ne gère pas les clés d’accès.',
    // Cancelled, timed out, or no matching passkey: the browser does not tell which
    NotAllowedError: 'La clé d’accès n’a pas été utilisée : réessayez.',
    InvalidStateError: 'Cette clé d’accès est déjà enregistrée sur votre compte.',
}

export default {
    selector: 'form.js-webauthn',
    connect(form) {
        const errorAlert = form.querySelector<HTMLElement>('[data-webauthn-error]')
        const showError = (message: string) => {
            if (errorAlert) {
                errorAlert.textContent = message
                errorAlert.hidden = false
            }
        }

        // Leaving the page aborts a pending ceremony: its prompt never submits a detached form
        const controller = new AbortController()

        const onSubmit = async (event: SubmitEvent) => {
            // Before any await: only a synchronous call cancels the submission
            event.preventDefault()
            if (errorAlert) {
                errorAlert.hidden = true
            }

            if (!window.PublicKeyCredential) {
                showError(MESSAGES.unsupported)
                return
            }

            const options = JSON.parse(form.dataset.webauthnOptions ?? '{}')
            try {
                const credential =
                    form.dataset.webauthnAction === 'register'
                        ? await navigator.credentials.create({
                              publicKey: decodeCreationOptions(options),
                              signal: controller.signal,
                          })
                        : await navigator.credentials.get({
                              publicKey: decodeRequestOptions(options),
                              signal: controller.signal,
                          })

                const field = form.querySelector<HTMLInputElement>('[data-webauthn-response]')
                if (!(credential instanceof PublicKeyCredential) || !field) {
                    return
                }
                field.value = JSON.stringify(encodeCredential(credential))
                // The native submit() fires no submit event, so it does not ask again
                form.submit()
            } catch (error) {
                const { name, message } = error instanceof Error ? error : { name: '', message: String(error) }
                if (name !== 'AbortError') {
                    showError(MESSAGES[name] ?? `La clé d’accès a échoué : ${message}`)
                }
            }
        }

        form.addEventListener('submit', onSubmit)

        return () => {
            form.removeEventListener('submit', onSubmit)
            controller.abort()
        }
    },
} satisfies Listener<HTMLFormElement>

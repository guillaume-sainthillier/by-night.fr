import { defineListener } from '@silarhi/app-js'
import { connect, SELECTOR } from '@silarhi/cap-bundle'

/**
 * The Cap challenge of the sign-up form (the "captcha" field, silarhi/cap-bundle in invisible mode). connect() listens
 * to the document once: the challenge is solved when the visitor reaches into the form, and a submit without a token
 * waits for it. The widget script and its solvers come from /bundles/silarhicap/widget/, on the sign-up page only.
 */
export default defineListener<HTMLInputElement>({ selector: SELECTOR, connect })

import { defineListener } from '@silarhi/app-js'

/**
 * Submit the form of an OTP input (components/OtpInput.html.twig) once its last digit is typed: Tabler's OtpInput
 * fires `complete.bs.otpInput` when the code reaches its length.
 */
export default defineListener<HTMLElement>({
    selector: '[data-otp-autosubmit]',
    connect(element) {
        const onComplete = () => element.closest('form')?.requestSubmit()

        element.addEventListener('complete.bs.otpInput', onComplete)

        return () => element.removeEventListener('complete.bs.otpInput', onComplete)
    },
})

import { defineListener } from '@silarhi/app-js'
import { copyLink } from '@/js/listeners/copy-url'

/** A phone or a tablet: there the share sheet of the system knows the visitor's apps; a computer keeps the site's menu */
const hasShareSheet = (): boolean =>
    typeof navigator.share === 'function' && window.matchMedia('(pointer: coarse)').matches

/**
 * Share the page a button carries in `data-share-url` (and `data-share-title`) with the share sheet of the system, or
 * copy its address where there is none.
 *
 * A button marked `data-share-native-only` starts hidden and shows only with a share sheet, hiding in its place the
 * `[data-share-fallback]` of its `[data-share]` group (the event page's share menu and copy button).
 */
export default defineListener<HTMLButtonElement>({
    selector: '[data-share-url]',
    connect(button, { app }) {
        const url = button.dataset.shareUrl ?? ''
        const sheet = hasShareSheet()

        if ('shareNativeOnly' in button.dataset) {
            if (!sheet) {
                return
            }
            button.hidden = false
            for (const fallback of button
                .closest('[data-share]')
                ?.querySelectorAll<HTMLElement>('[data-share-fallback]') ?? []) {
                fallback.hidden = true
            }
        }

        const share = () => {
            if (sheet) {
                // Closing the sheet rejects too: nothing to say then
                navigator.share({ title: button.dataset.shareTitle, url }).catch(() => undefined)

                return
            }

            copyLink(app, url)
        }

        button.addEventListener('click', share)

        return () => button.removeEventListener('click', share)
    },
})

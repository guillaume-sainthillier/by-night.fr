import { defineListener } from '@silarhi/app-js'
import type { create } from '@/js/services/ui/FancyboxService'

/**
 * Open every `.image-gallery` link in a Fancybox lightbox instead of following
 * it to the full-size image.
 *
 * Fancybox (jQuery plugin + CSS) is loaded on first use only, so pages without
 * a gallery never pay for it. Being a listener rather than a boot module, it
 * also covers galleries mounted after an AJAX insert: agenda pagination,
 * the "Plus de sorties" and "Plus de membres" buttons.
 */
export default defineListener<HTMLElement>({
    selector: '.image-gallery',
    connect(element) {
        let lightbox: ReturnType<typeof create> | null = null
        let disconnected = false

        import('@/js/services/ui/FancyboxService')
            .then((fancybox) => {
                // The element may have been unmounted while the chunk was loading
                if (disconnected) return
                lightbox = fancybox.create({ element })
            })
            // The chunk or its stylesheet could not be fetched (flaky network, blocker): the link opens the image
            .catch(() => {})

        return () => {
            disconnected = true
            lightbox?.destroy()
        }
    },
})

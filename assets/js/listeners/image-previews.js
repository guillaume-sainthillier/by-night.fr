/**
 * Open every `.image-gallery` link in a Fancybox lightbox instead of following
 * it to the full-size image.
 *
 * Fancybox (jQuery plugin + CSS) is loaded on first use only, so pages without
 * a gallery never pay for it. Being a listener rather than a boot module, it
 * also covers galleries mounted after an AJAX insert: agenda pagination,
 * search "Plus", user events "Charger plus".
 *
 * @type {Listener}
 */
export default {
    selector: '.image-gallery',
    connect(element) {
        let lightbox = null
        let disconnected = false

        import('@/js/services/ui/FancyboxService').then((fancybox) => {
            // The element may have been unmounted while the chunk was loading
            if (disconnected) return
            lightbox = fancybox.create({ element })
        })

        return () => {
            disconnected = true
            lightbox?.destroy()
        }
    },
}

/**
 * Lazy-load Fancybox on every image gallery present at boot.
 * Runs once at App.start().
 */
export default function init() {
    if (document.querySelector('.image-gallery')) {
        import('@/js/services/ui/FancyboxService').then((module) => {
            document.querySelectorAll('.image-gallery').forEach((el) => {
                module.create({ element: el })
            })
        })
    }
}

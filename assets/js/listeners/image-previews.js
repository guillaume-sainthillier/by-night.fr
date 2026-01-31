export default function init(di, container) {
    if (container.querySelector('.image-gallery')) {
        import('@/js/services/ui/FancyboxService').then((module) => {
            container.querySelectorAll('.image-gallery').forEach((el) => {
                module.create({ element: el })
            })
        })
    }
}

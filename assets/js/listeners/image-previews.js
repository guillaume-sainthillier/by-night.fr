export default function init(di, container) {
    if ($('.image-gallery', container).length > 0) {
        import('../lazy-listeners/image-previews').then((module) => module.default(container));
    }
}

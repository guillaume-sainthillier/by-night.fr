// EasyAdmin entry point (registered in App\Controller\Admin\DashboardController::configureAssets()).
// Standalone on purpose: EasyAdmin does not load the front-office app bundle (window.App, listeners...).

/**
 * No client-side validation: the server validates the upload (Assert\Image + maxSize on the entities).
 *
 * @param field the [data-image-preview] wrapper rendered by admin/form/vich_image_theme.html.twig
 */
function previewImage(field: HTMLElement, file: File | undefined) {
    const current = field.querySelector<HTMLElement>('[data-image-preview-current]')
    const preview = field.querySelector<HTMLElement>('[data-image-preview-new]')
    const image = preview?.querySelector('img')
    if (!preview || !image) {
        return
    }

    if (image.src.startsWith('blob:')) {
        URL.revokeObjectURL(image.src)
    }
    image.removeAttribute('src')

    if (file) {
        image.src = URL.createObjectURL(file)
    }
    preview.hidden = !file
    if (current) {
        current.hidden = !!file
    }
}

// Delegated so it also covers fields added later (e.g. inside collection entries)
document.addEventListener('change', (event) => {
    const input = event.target
    const field = input instanceof HTMLInputElement ? input.closest<HTMLElement>('[data-image-preview]') : null
    if (!(input instanceof HTMLInputElement) || !field) {
        return
    }

    if (input.matches('[data-image-preview-input]')) {
        previewImage(field, input.files?.[0])
    } else if (input.matches('[data-image-preview-delete]')) {
        field.querySelector('[data-image-preview-current]')?.classList.toggle('opacity-25', input.checked)
    }
})

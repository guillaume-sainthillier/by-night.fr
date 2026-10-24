import { defineListener } from '@silarhi/app-js'

const DROPZONE_SELECTOR = '.dropzone'
const FILE_INPUT_SELECTOR = 'input[type="file"]'
const PREVIEW_SELECTOR = '.dropzone-preview'
const PLACEHOLDER_SELECTOR = '.dropzone-placeholder'
const ERROR_CLASS = 'dropzone-error'

// Decimal units, as the File constraint counts them
const kilobytes = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 })
const megabytes = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 1 })

/**
 * What the field takes: its `accept` attribute (".csv,.xlsx", "image/*"), images when it has none (the poster's
 * field, the dropzone's first use).
 */
function acceptedTypes(input: HTMLInputElement): string[] {
    return (input.getAttribute('accept') || 'image/*')
        .split(',')
        .map((type) => type.trim().toLowerCase())
        .filter(Boolean)
}

/**
 * Whether a dropped file is one the field takes, by its extension or its type, as the browser's picker filters it.
 */
export function accepts(file: { name: string; type: string }, accepted: string[]): boolean {
    const name = file.name.toLowerCase()
    const type = (file.type || '').toLowerCase()

    return accepted.some((rule) => {
        if (rule.startsWith('.')) {
            return name.endsWith(rule)
        }
        if (rule.endsWith('/*')) {
            return type.startsWith(rule.slice(0, -1))
        }

        return type === rule
    })
}

/**
 * A file's weight the way people read it: "850 Ko", "1,4 Mo".
 *
 * @param size in bytes
 */
export function formatSize(size: number): string {
    return size < 1e6 ? `${kilobytes.format(Math.max(1, size / 1e3))} Ko` : `${megabytes.format(size / 1e6)} Mo`
}

/**
 * Shows why a dropped file was refused, under the dropzone; nothing when `message` is null.
 */
function showError(dropzone: Element, message: string | null) {
    let error = dropzone.parentElement?.querySelector<HTMLElement>(`:scope > .${ERROR_CLASS}`)
    if (!error && message) {
        error = document.createElement('div')
        error.className = `invalid-feedback d-block ${ERROR_CLASS}`
        dropzone.after(error)
    }
    if (error) {
        error.textContent = message ?? ''
        error.hidden = !message
    }
}

/**
 * Shows the picked file: an image as itself, any other file as its name and weight.
 */
function showPreview(preview: Element, file: File, placeholder: Element | null) {
    const show = () => {
        preview.classList.remove('d-none')
        placeholder?.classList.add('d-none')
    }

    if (file.type.startsWith('image/')) {
        const reader = new FileReader()
        reader.onload = () => {
            if (typeof reader.result !== 'string') {
                return
            }
            const img = document.createElement('img')
            img.src = reader.result
            img.alt = 'Aperçu'
            img.className = 'img-fluid rounded'
            preview.replaceChildren(img)
            show()
        }
        reader.readAsDataURL(file)

        return
    }

    const name = document.createElement('div')
    name.className = 'dropzone-file-name'
    name.textContent = file.name
    const meta = document.createElement('div')
    meta.className = 'dropzone-hint text-muted'
    meta.textContent = `${formatSize(file.size)} · cliquez ou glissez un autre fichier pour le remplacer`
    preview.replaceChildren(name, meta)
    show()
}

const handleDragOver = (e: DragEvent) => {
    e.preventDefault()
    e.stopPropagation()
    if (e.currentTarget instanceof Element) {
        e.currentTarget.classList.add('dropzone-dragover')
    }
}

const handleDragLeave = (e: DragEvent) => {
    e.preventDefault()
    e.stopPropagation()
    if (e.currentTarget instanceof Element) {
        e.currentTarget.classList.remove('dropzone-dragover')
    }
}

const handleDrop = (e: DragEvent) => {
    e.preventDefault()
    e.stopPropagation()

    const dropzone = e.currentTarget
    if (!(dropzone instanceof Element)) {
        return
    }
    dropzone.classList.remove('dropzone-dragover')

    const fileInput = dropzone.querySelector<HTMLInputElement>(FILE_INPUT_SELECTOR)
    // Only handle single file
    const file = e.dataTransfer?.files[0]
    if (!fileInput || !file) {
        return
    }

    if (!accepts(file, acceptedTypes(fileInput))) {
        showError(dropzone, `« ${file.name} » n’est pas un fichier accepté ici.`)

        return
    }

    showError(dropzone, null)
    // Create a new DataTransfer to set files on the input
    const dataTransfer = new DataTransfer()
    dataTransfer.items.add(file)
    fileInput.files = dataTransfer.files

    // Trigger change event to update preview
    fileInput.dispatchEvent(new Event('change', { bubbles: true }))
}

const handleFileChange = (e: Event) => {
    const fileInput = e.target
    if (!(fileInput instanceof HTMLInputElement)) {
        return
    }
    const dropzone = fileInput.closest(DROPZONE_SELECTOR)
    if (!dropzone) {
        return
    }

    const preview = dropzone.querySelector(PREVIEW_SELECTOR)
    const placeholder = dropzone.querySelector(PLACEHOLDER_SELECTOR)

    if (!fileInput.files?.length) {
        // No file selected, show placeholder
        if (preview) {
            preview.replaceChildren()
            preview.classList.add('d-none')
        }
        placeholder?.classList.remove('d-none')

        return
    }

    const file = fileInput.files[0]
    if (file && preview && accepts(file, acceptedTypes(fileInput))) {
        showError(dropzone, null)
        showPreview(preview, file, placeholder)
    }
}

const handleClick = (e: MouseEvent) => {
    const { target, currentTarget: dropzone } = e
    // Only trigger file input if clicking on the dropzone area itself, not on the file input or existing preview link
    if (!(target instanceof Element) || target.closest('a') || target.closest('input') || target.closest('button')) {
        return
    }
    if (!(dropzone instanceof Element)) {
        return
    }

    const fileInput = dropzone.querySelector<HTMLInputElement>(FILE_INPUT_SELECTOR)
    if (fileInput) {
        fileInput.click()
    }
}

/**
 * Dropzone: drag & drop or click-to-pick a single file, of the types its field accepts (images when it says
 * nothing), with a preview: the image, or the file's name and weight.
 */
export default defineListener<HTMLElement>({
    selector: DROPZONE_SELECTOR,
    connect(dropzone) {
        const fileInput = dropzone.querySelector(FILE_INPUT_SELECTOR)

        // Add event listeners
        dropzone.addEventListener('dragover', handleDragOver)
        dropzone.addEventListener('dragleave', handleDragLeave)
        dropzone.addEventListener('drop', handleDrop)
        dropzone.addEventListener('click', handleClick)

        if (fileInput) {
            fileInput.addEventListener('change', handleFileChange)
        }

        return () => {
            dropzone.removeEventListener('dragover', handleDragOver)
            dropzone.removeEventListener('dragleave', handleDragLeave)
            dropzone.removeEventListener('drop', handleDrop)
            dropzone.removeEventListener('click', handleClick)

            if (fileInput) {
                fileInput.removeEventListener('change', handleFileChange)
            }
        }
    },
})

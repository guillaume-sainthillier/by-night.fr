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
 *
 * @param {HTMLInputElement} input
 * @returns {string[]}
 */
function acceptedTypes(input) {
    return (input.getAttribute('accept') || 'image/*')
        .split(',')
        .map((type) => type.trim().toLowerCase())
        .filter(Boolean)
}

/**
 * Whether a dropped file is one the field takes, by its extension or its type, as the browser's picker filters it.
 *
 * @param {{name: string, type: string}} file
 * @param {string[]} accepted
 * @returns {boolean}
 */
export function accepts(file, accepted) {
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
 * @param {number} size in bytes
 * @returns {string}
 */
export function formatSize(size) {
    return size < 1e6 ? `${kilobytes.format(Math.max(1, size / 1e3))} Ko` : `${megabytes.format(size / 1e6)} Mo`
}

/**
 * Shows why a dropped file was refused, under the dropzone; nothing when `message` is null.
 *
 * @param {Element} dropzone
 * @param {string|null} message
 */
function showError(dropzone, message) {
    let error = dropzone.parentElement?.querySelector(`:scope > .${ERROR_CLASS}`)
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
 *
 * @param {Element} preview
 * @param {File} file
 * @param {Element|null} placeholder
 */
function showPreview(preview, file, placeholder) {
    const show = () => {
        preview.classList.remove('d-none')
        placeholder?.classList.add('d-none')
    }

    if (file.type.startsWith('image/')) {
        const reader = new FileReader()
        reader.onload = (event) => {
            const img = document.createElement('img')
            img.src = event.target.result
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

const handleDragOver = (e) => {
    e.preventDefault()
    e.stopPropagation()
    e.currentTarget.classList.add('dropzone-dragover')
}

const handleDragLeave = (e) => {
    e.preventDefault()
    e.stopPropagation()
    e.currentTarget.classList.remove('dropzone-dragover')
}

const handleDrop = (e) => {
    e.preventDefault()
    e.stopPropagation()

    const dropzone = e.currentTarget
    dropzone.classList.remove('dropzone-dragover')

    const fileInput = dropzone.querySelector(FILE_INPUT_SELECTOR)
    if (!fileInput || !e.dataTransfer.files.length) {
        return
    }

    // Only handle single file
    const file = e.dataTransfer.files[0]
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

const handleFileChange = (e) => {
    const fileInput = e.target
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
    if (preview && accepts(file, acceptedTypes(fileInput))) {
        showError(dropzone, null)
        showPreview(preview, file, placeholder)
    }
}

const handleClick = (e) => {
    // Only trigger file input if clicking on the dropzone area itself, not on the file input or existing preview link
    if (e.target.closest('a') || e.target.closest('input') || e.target.closest('button')) {
        return
    }

    const dropzone = e.currentTarget
    const fileInput = dropzone.querySelector(FILE_INPUT_SELECTOR)
    if (fileInput) {
        fileInput.click()
    }
}

/**
 * Dropzone: drag & drop or click-to-pick a single file, of the types its field accepts (images when it says
 * nothing), with a preview: the image, or the file's name and weight.
 *
 * @type {Listener}
 */
export default {
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
}

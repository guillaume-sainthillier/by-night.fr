const DROPZONE_SELECTOR = '.dropzone'
const FILE_INPUT_SELECTOR = 'input[type="file"]'
const PREVIEW_SELECTOR = '.dropzone-preview'
const PLACEHOLDER_SELECTOR = '.dropzone-placeholder'

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

    // Validate file is an image
    if (!file.type.startsWith('image/')) {
        return
    }

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

    if (!fileInput.files || !fileInput.files.length) {
        // No file selected, show placeholder
        if (preview) {
            preview.innerHTML = ''
            preview.classList.add('d-none')
        }
        if (placeholder) {
            placeholder.classList.remove('d-none')
        }
        return
    }

    const file = fileInput.files[0]
    if (!file.type.startsWith('image/')) {
        return
    }

    // Create preview
    const reader = new FileReader()
    reader.onload = (event) => {
        if (preview) {
            preview.innerHTML = `<img src="${event.target.result}" alt="Preview" class="img-fluid rounded" />`
            preview.classList.remove('d-none')
        }
        if (placeholder) {
            placeholder.classList.add('d-none')
        }
    }
    reader.readAsDataURL(file)
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

export default () => {
    document.querySelectorAll(DROPZONE_SELECTOR).forEach((dropzone) => {
        // Remove existing listeners by cloning (simple approach for re-init)
        const fileInput = dropzone.querySelector(FILE_INPUT_SELECTOR)

        // Add event listeners
        dropzone.addEventListener('dragover', handleDragOver)
        dropzone.addEventListener('dragleave', handleDragLeave)
        dropzone.addEventListener('drop', handleDrop)
        dropzone.addEventListener('click', handleClick)

        if (fileInput) {
            fileInput.addEventListener('change', handleFileChange)
        }
    })
}

import $ from 'jquery'

/**
 * Submit the form designated by `data-target` when the element is clicked.
 */
export default {
    selector: 'input[data-target], button[data-target], a[data-target]',
    connect(elem) {
        const onClick = (e) => {
            const targetSelector = $(elem).data('target')
            const target = $(targetSelector)
            if (!target.length) {
                throw new Error(`No target available for ${targetSelector}`)
            }

            e.preventDefault()
            $(targetSelector).submit()
        }

        elem.addEventListener('click', onClick)

        return () => elem.removeEventListener('click', onClick)
    },
}

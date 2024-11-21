import $ from 'jquery'
import {findAll, on} from "@/js/utils/dom"

export default (di, container) => {
    const elems = findAll('input[data-target], button[data-target], a[data-target]', container)

    if (!elems.length) {
        return
    }

    elems.forEach((elem) => {
        on(elem, 'click', (e) => {
            const targetSelector = $(elem).data('target')
            const target = $(targetSelector)
            if (!target.length) {
                throw new Error(`No target available for ${targetSelector}`)
            }

            e.preventDefault()
            $(targetSelector).submit()
        })
    })
}

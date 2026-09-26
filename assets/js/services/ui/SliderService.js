import noUiSlider from 'nouislider'

import '@/scss/lazy-components/_slider.scss'

/**
 * Turns a number input into a slider. The input stays in the form, hidden, and keeps the value the form submits: the
 * slider only writes into it, and shows the value at the end of the input's label.
 */
export function create({ element, range, snap = false, unit = '', label = null }) {
    const labelEl = element.id ? document.querySelector(`label[for="${element.id}"]`) : null
    const ariaLabel = label ?? labelEl?.textContent.trim() ?? ''
    // A range edge is either a value or a [value, step] pair
    const min = [range.min].flat()[0]
    const format = {
        to: (value) => `${Math.round(value)}${unit ? ` ${unit}` : ''}`,
        from: (value) => Number.parseFloat(value),
    }

    const slider = document.createElement('div')
    element.after(slider)
    element.hidden = true

    // The value, read at the end of the label; screen readers get it from the handle's aria-valuetext
    const output = document.createElement('span')
    output.className = 'form-label-description fw-bold text-primary'
    output.setAttribute('aria-hidden', 'true')
    labelEl?.append(output)

    const instance = noUiSlider.create(slider, {
        start: Number.parseFloat(element.value) || min,
        range,
        snap,
        connect: 'lower',
        ariaFormat: format,
        handleAttributes: [{ 'aria-label': ariaLabel }],
    })

    instance.on('update', ([value]) => {
        element.value = Math.round(value)
        output.textContent = format.to(value)
    })

    return {
        destroy: () => {
            instance.destroy()
            slider.remove()
            output.remove()
            element.hidden = false
        },
    }
}

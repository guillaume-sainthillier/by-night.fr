import { defineListener } from '@silarhi/app-js'
import { Tooltip } from '@tabler/core'

/**
 * Activate a Bootstrap tooltip on each tooltip-toggle element. Disposes it on
 * disconnect.
 */
export default defineListener<HTMLElement>({
    selector: '[data-bs-toggle="tooltip"]',
    connect(element) {
        const tooltip = Tooltip.getOrCreateInstance(element)

        return () => tooltip.dispose()
    },
})

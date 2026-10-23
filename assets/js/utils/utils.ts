declare global {
    interface Window {
        /** The element a social login popup was opened from, read back by templates/security/connect-success.html.twig */
        parent_elem?: Element
    }
}

export const isTouchDevice = () => {
    return 'ontouchstart' in window || navigator.maxTouchPoints > 0
}

export const popup = (href: string, parent: Element) => {
    window.parent_elem = parent

    const width = 520
    const height = 350
    const leftPosition = window.screen.width / 2 - (width / 2 + 10)
    const topPosition = window.screen.height / 2 - (height / 2 + 50)
    const windowFeatures = `status=no,height=${height},width=${width},left=${leftPosition},top=${topPosition},screenX=${leftPosition},screenY=${topPosition},toolbar=0,status=0`

    window.open(href, 'sharer', windowFeatures)
}

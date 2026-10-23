import { type ComponentType, h } from 'preact'
import { render } from 'preact-render-to-string'

export const iconHtml = (Icon: ComponentType<{ className?: string }>, className = '') =>
    render(h(Icon, { className: `icon ${className}`.trim() }))

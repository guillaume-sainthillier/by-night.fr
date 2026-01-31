/** @jsx h */
// Utility for rendering Preact icon components to HTML strings
import { h } from 'preact'
import { render } from 'preact-render-to-string'

export const iconHtml = (Icon, className = '') => render(h(Icon, { className: `icon ${className}`.trim() }))

/**
 * jQuery plugins registered at runtime, without types of their own.
 */
interface JQuery<TElement = HTMLElement> {
    /**
     * Bootstrap's Modal plugin (registered on window.jQuery, see jquery-global.ts), with the helpers of overrides.ts.
     * Like every Bootstrap plugin, it returns the collection, whatever the action returns.
     */
    modal(action?: 'show' | 'hide' | 'toggle' | 'dispose' | 'loading' | 'getBody'): this
    modal(action: 'setError', message: string): this

    /** Bootstrap's Tooltip plugin */
    tooltip(action?: 'show' | 'hide' | 'toggle' | 'dispose'): this

    /** Bootstrap's Alert plugin */
    alert(action?: 'close' | 'dispose'): this

    /** fancybox 3.0.1 (fancybox/dist/js/jquery.fancybox): opens the collection's links in a lightbox on click */
    fancybox(options?: FancyboxOptions): this

    /** summernote 0.9.1 (summernote/dist/summernote-bs5) */
    summernote(options?: SummernoteOptions): this
    summernote(action: 'code'): string
    summernote(action: 'code', html: string): this
    summernote(action: 'isEmpty'): boolean
    summernote(action: 'destroy'): this
}

interface FancyboxOptions {
    helpers?: {
        title?: { type?: 'float' | 'inside' | 'outside' | 'over'; position?: 'top' | 'bottom' }
        overlay?: { locked?: boolean }
    }
}

/** A toolbar group: its name, then its buttons */
type SummernoteToolbarGroup = [string, string[]]

interface SummernoteOptions {
    lang?: string
    toolbar?: SummernoteToolbarGroup[]
    height?: number
    codemirror?: { mode?: string; htmlMode?: boolean }
}

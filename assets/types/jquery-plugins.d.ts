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
}

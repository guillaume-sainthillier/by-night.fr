import $ from 'jquery'

/*
 * Expose jQuery as window.$ / window.jQuery before any legacy plugin is evaluated.
 *
 * Several dependencies read jQuery as a free global at evaluation time rather than
 * importing it: fancybox (`}(window, document, jQuery))`), morris.js (`$ = jQuery`) and
 * summernote's language packs (`})(jQuery)`). Bootstrap 5 also only registers its jQuery
 * plugins ($.fn.tooltip, $.fn.modal…) when it finds window.jQuery. Encore's
 * autoProvideVariables() rewrote those references at build time; Vite has no equivalent.
 *
 * Import `$` from this module instead of 'jquery' in any file that loads such a plugin, and
 * keep it first: ES modules evaluate in import order, so the globals are set before the
 * plugin runs, whichever entry or <script> tag pulled it in.
 *
 * Inline <script type="module"> blocks in Twig templates also rely on window.$.
 */
window.$ = window.jQuery = $

export default $

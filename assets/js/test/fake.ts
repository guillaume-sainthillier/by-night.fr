/**
 * The tests run in Node, without a DOM: they hand the code under test plain objects that implement only what it
 * touches (an element's dataset and listeners, a toast manager's createToast…). This is the one place where such a
 * double is passed off as the type the code expects.
 */
export const fake = <T>(double: object): T => double as T

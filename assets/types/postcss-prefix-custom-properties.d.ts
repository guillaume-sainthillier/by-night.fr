declare module 'postcss-prefix-custom-properties' {
    import type { Helpers, Plugin, Root } from 'postcss'

    interface Options {
        prefix?: string
        /** Custom properties left alone, matched with or without their leading "--" */
        ignore?: Array<string | RegExp>
    }

    interface PrefixCustomPropertiesPlugin extends Plugin {
        Once(root: Root, helpers: Helpers): void
    }

    const prefixCustomProperties: {
        (options?: Options): PrefixCustomPropertiesPlugin
        postcss: true
    }

    export default prefixCustomProperties
}

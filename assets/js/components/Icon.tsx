import { mapNodes, resolveIcon, splitAttributes, type UXIconData } from '@silarhi/ux-icons-js'
import { h, type SVGAttributes, type VNode } from 'preact'

type IconProps = Omit<SVGAttributes<SVGSVGElement>, 'icon' | 'children' | 'dangerouslySetInnerHTML'> & {
    icon: UXIconData
}

/**
 * A dumped icon (`@icons`) as a Preact element, with the same attributes as `<twig:Icon>`: the package ships React
 * and Vue adapters only. Preact takes HTML attribute names (`class`, `stroke-width`) as they are, so the resolved
 * attributes need no renaming; `class` replaces the configured `icon` class, as in Twig.
 */
export const Icon = ({ icon, ...props }: IconProps): VNode => {
    // Strings, numbers and booleans follow the ux_icon() rules, the rest (handlers, refs) goes to Preact as is
    const [attributes, passthrough] = splitAttributes(props)

    return h(
        'svg',
        { ...resolveIcon(icon, attributes), ...passthrough },
        ...mapNodes<VNode>(icon.children, (tag, nodeAttributes, children) => h(tag, nodeAttributes, ...children))
    )
}

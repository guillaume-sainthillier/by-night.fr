import { h } from 'preact';
const SvgCrosshair = (props) => (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="1em" height="1em" {...props}>
        <g fill="none" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}>
            <circle cx={12} cy={12} r={10} />
            <path d="M22 12h-4M6 12H2m10-6V2m0 20v-4" />
        </g>
    </svg>
);
export default SvgCrosshair;

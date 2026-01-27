import { h } from 'preact';
const SvgSearch = (props) => (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="1em" height="1em" {...props}>
        <g fill="none" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}>
            <path d="m21 21-4.34-4.34" />
            <circle cx={11} cy={11} r={8} />
        </g>
    </svg>
);
export default SvgSearch;

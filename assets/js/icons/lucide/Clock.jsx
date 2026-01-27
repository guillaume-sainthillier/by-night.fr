import { h } from 'preact';
const SvgClock = (props) => (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="1em" height="1em" {...props}>
        <g fill="none" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}>
            <path d="M12 6v6l4 2" />
            <circle cx={12} cy={12} r={10} />
        </g>
    </svg>
);
export default SvgClock;

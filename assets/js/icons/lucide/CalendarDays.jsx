import { h } from 'preact';
const SvgCalendarDays = (props) => (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="1em" height="1em" {...props}>
        <g fill="none" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}>
            <path d="M8 2v4m8-4v4" />
            <rect width={18} height={18} x={3} y={4} rx={2} />
            <path d="M3 10h18M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01M16 18h.01" />
        </g>
    </svg>
);
export default SvgCalendarDays;

import { h } from 'preact';
const SvgChevronLeft = (props) => (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="1em" height="1em" {...props}>
        <path
            fill="none"
            stroke="currentColor"
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="m15 18-6-6 6-6"
        />
    </svg>
);
export default SvgChevronLeft;

import { h } from 'preact';
const SvgX = (props) => (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="1em" height="1em" {...props}>
        <path
            fill="none"
            stroke="currentColor"
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M18 6 6 18M6 6l12 12"
        />
    </svg>
);
export default SvgX;

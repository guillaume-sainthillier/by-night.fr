import { h } from 'preact';
const SvgBarChart = (props) => (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="1em" height="1em" {...props}>
        <path
            fill="none"
            stroke="currentColor"
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M5 21v-6m7 6V9m7 12V3"
        />
    </svg>
);
export default SvgBarChart;

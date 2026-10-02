// Small stroke icons for form controls. Decorative: the controls carry the
// accessible names.

const base = {
  width: 16,
  height: 16,
  viewBox: '0 0 16 16',
  fill: 'none',
  stroke: 'currentColor',
  strokeWidth: 1.6,
  strokeLinecap: 'round',
  strokeLinejoin: 'round',
  'aria-hidden': true,
} as const;

export const ChevronDownIcon = () => (
  <svg {...base} className="icon icon-chevron">
    <path d="m4 6 4 4 4-4" />
  </svg>
);

export const ChevronLeftIcon = () => (
  <svg {...base} className="icon">
    <path d="m10 4-4 4 4 4" />
  </svg>
);

export const ChevronRightIcon = () => (
  <svg {...base} className="icon">
    <path d="m6 4 4 4-4 4" />
  </svg>
);

export const CheckIcon = () => (
  <svg {...base} className="icon icon-check">
    <path d="m3.5 8.5 3 3 6-7" />
  </svg>
);

export const CalendarIcon = () => (
  <svg {...base} className="icon">
    <rect x="2.5" y="3.5" width="11" height="10" rx="1.5" />
    <path d="M2.5 6.5h11M5.5 2v3M10.5 2v3" />
  </svg>
);

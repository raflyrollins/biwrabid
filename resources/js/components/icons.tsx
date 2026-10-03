import type { SVGProps } from 'react';

/**
 * Hand-rolled icons.
 *
 * The project ships no icon package, so these are drawn inline instead of
 * adding a dependency for two glyphs. They inherit `currentColor` and take
 * their box from the `size` prop, so a button keeps its own text colour token
 * (`text-fg-brand`, `text-fg-danger`, …) exactly like a text link did.
 */
type IconProps = Omit<SVGProps<SVGSVGElement>, 'width' | 'height'> & {
    size?: number;
};

function Icon({ size = 20, children, ...props }: IconProps) {
    return (
        <svg
            aria-hidden="true"
            focusable="false"
            width={size}
            height={size}
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth={1.75}
            strokeLinecap="round"
            strokeLinejoin="round"
            {...props}
        >
            {children}
        </svg>
    );
}

export function PencilIcon(props: IconProps) {
    return (
        <Icon {...props}>
            <path d="M12 20h9" />
            <path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z" />
        </Icon>
    );
}

export function TrashIcon(props: IconProps) {
    return (
        <Icon {...props}>
            <path d="M3 6h18" />
            <path d="M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2" />
            <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" />
            <path d="M10 11v6" />
            <path d="M14 11v6" />
        </Icon>
    );
}

export function PaperclipIcon(props: IconProps) {
    return (
        <Icon {...props}>
            <path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48" />
        </Icon>
    );
}

export function DocumentIcon(props: IconProps) {
    return (
        <Icon {...props}>
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
            <path d="M14 2v6h6" />
            <path d="M9 13h6" />
            <path d="M9 17h6" />
        </Icon>
    );
}

export function ImageIcon(props: IconProps) {
    return (
        <Icon {...props}>
            <rect x="3" y="3" width="18" height="18" rx="2" />
            <circle cx="8.5" cy="8.5" r="1.5" />
            <path d="M21 15l-5-5L5 21" />
        </Icon>
    );
}

export function MessageIcon(props: IconProps) {
    return (
        <Icon {...props}>
            <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z" />
        </Icon>
    );
}

export function ShieldIcon(props: IconProps) {
    return (
        <Icon {...props}>
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
        </Icon>
    );
}

export function SendIcon(props: IconProps) {
    return (
        <Icon {...props}>
            <path d="M22 2L11 13" />
            <path d="M22 2l-7 20-4-9-9-4 20-7z" />
        </Icon>
    );
}

export function ArrowLeftIcon(props: IconProps) {
    return (
        <Icon {...props}>
            <path d="M19 12H5" />
            <path d="M12 19l-7-7 7-7" />
        </Icon>
    );
}

export function WarningIcon(props: IconProps) {
    return (
        <Icon {...props}>
            <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
            <path d="M12 9v4" />
            <path d="M12 17h.01" />
        </Icon>
    );
}

export function CheckCircleIcon(props: IconProps) {
    return (
        <Icon {...props}>
            <circle cx="12" cy="12" r="9" />
            <path d="m8.5 12.5 2.5 2.5 4.5-5" />
        </Icon>
    );
}

export function XCircleIcon(props: IconProps) {
    return (
        <Icon {...props}>
            <circle cx="12" cy="12" r="9" />
            <path d="m9 9 6 6" />
            <path d="m15 9-6 6" />
        </Icon>
    );
}

/**
 * The three delivery states of a message the viewer sent, in the order they
 * replace one another.
 *
 * Drawn slightly heavier than the icons around them: at the 10px the bubble
 * footer renders them, a 1.75 stroke all but disappears and the state stops being
 * readable at all — which is the whole point of showing it.
 */
const tickStroke = { strokeWidth: 2.25 } as const;

export function ClockIcon(props: IconProps) {
    return (
        <Icon {...tickStroke} {...props}>
            <circle cx="12" cy="12" r="9" />
            <path d="M12 7.5V12l3 2" />
        </Icon>
    );
}

export function CheckIcon(props: IconProps) {
    return (
        <Icon {...tickStroke} {...props}>
            <path d="m4.5 12.5 5 5 10-11" />
        </Icon>
    );
}

export function CheckCheckIcon(props: IconProps) {
    return (
        <Icon {...tickStroke} {...props}>
            <path d="m1.5 12.5 4.5 4.5 7.5-8.5" />
            <path d="m9 15.5 2 2 11.5-13" />
        </Icon>
    );
}

import { usePage } from '@inertiajs/react';
import {
    useLayoutEffect,
    useRef,
    type ChangeEvent,
    type ComponentProps,
} from 'react';
import { TextInput } from '@/components/ui/text-input';
import { formatThousands } from '@/lib/format';

type NumberInputProps = Omit<
    ComponentProps<typeof TextInput>,
    'value' | 'type'
> & {
    /** The stored value: digits only, no separators. */
    value: string;
    onValueChange: (value: string) => void;
};

function countDigits(value: string): number {
    return value.replace(/\D/g, '').length;
}

/**
 * A digits-only text field that groups thousands while you type.
 *
 * Grouping is display only — the parent always receives a plain digit string
 * (e.g. `1500000`), so nothing reformatted ever reaches the database.
 */
export function NumberInput({
    value,
    onValueChange,
    onChange,
    ...props
}: NumberInputProps) {
    const { locale } = usePage().props;
    const inputRef = useRef<HTMLInputElement>(null);
    const caretDigits = useRef<number | null>(null);

    const display = formatThousands(value, locale);

    useLayoutEffect(() => {
        const input = inputRef.current;
        const wanted = caretDigits.current;

        if (input === null || wanted === null) {
            return;
        }

        caretDigits.current = null;

        let seen = 0;
        let position = display.length;

        for (let index = 0; index < display.length; index += 1) {
            if (/\d/.test(display[index])) {
                seen += 1;

                if (seen === wanted) {
                    position = index + 1;
                    break;
                }
            }
        }

        input.setSelectionRange(position, position);
    }, [display]);

    function handleChange(event: ChangeEvent<HTMLInputElement>) {
        const next = event.target.value;
        const caret = event.target.selectionStart ?? next.length;

        caretDigits.current = countDigits(next.slice(0, caret));

        onChange?.(event);

        onValueChange(next.replace(/\D/g, '').replace(/^0+(?=\d)/, ''));
    }

    return (
        <TextInput
            {...props}
            ref={inputRef}
            type="text"
            inputMode="numeric"
            autoComplete="off"
            value={display}
            onChange={handleChange}
        />
    );
}

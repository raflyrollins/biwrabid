import { usePage } from '@inertiajs/react';

export type TranslationValues = Record<string, string | number>;

function lookup(
    translations: Record<string, unknown>,
    key: string,
): string | null {
    const value = key.split('.').reduce<unknown>((carry, segment) => {
        if (typeof carry !== 'object' || carry === null) {
            return undefined;
        }

        return (carry as Record<string, unknown>)[segment];
    }, translations);

    return typeof value === 'string' ? value : null;
}

export function useTranslation() {
    const { translations } = usePage().props;

    function t(key: string, values: TranslationValues = {}): string {
        const template = lookup(translations, key) ?? key;

        return template.replace(/:(\w+)/g, (match, name: string) =>
            name in values ? String(values[name]) : match,
        );
    }

    return { t };
}

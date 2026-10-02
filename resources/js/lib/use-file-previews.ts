import { useEffect, useMemo } from 'react';

export function fileKey(file: File): string {
    return `${file.name}:${file.size}:${file.type}`;
}

/**
 * Object URLs for `files`, memoised on the file signature.
 *
 * Inertia deep-clones the form data on every keystroke, so `files` arrives as
 * a brand new array of brand new `File` objects each time any *other* field
 * changes. Memoising on the signature instead of the array identity keeps the
 * URLs — and therefore every `<img src>` — stable, which is what stops the
 * thumbnails from flickering while the seller types.
 *
 * The signature stays order-sensitive on purpose: the previews are consumed by
 * index, so a reorder has to rebuild them in the new order.
 */
export function useFilePreviews(files: File[]): string[] {
    const signature = files.map(fileKey).join('|');

    const previews = useMemo(
        () => files.map((file) => URL.createObjectURL(file)),
        [signature],
    );

    useEffect(
        () => () => {
            previews.forEach((url) => URL.revokeObjectURL(url));
        },
        [previews],
    );

    return previews;
}

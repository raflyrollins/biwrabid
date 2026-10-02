import { useRef, useState, type DragEvent } from 'react';
import { useTranslation } from '@/lib/i18n';
import { fileKey, useFilePreviews } from '@/lib/use-file-previews';
import { cn } from '@/lib/utils';

type ScreenshotManagerProps = {
    files: File[];
    onChange: (files: File[]) => void;
    max: number;
    id?: string;
};

export function ScreenshotManager({
    files,
    onChange,
    max,
    id = 'screenshots',
}: ScreenshotManagerProps) {
    const { t } = useTranslation();
    const inputRef = useRef<HTMLInputElement>(null);
    const [dragIndex, setDragIndex] = useState<number | null>(null);

    const previews = useFilePreviews(files);

    const remaining = max - files.length;

    function move(from: number, to: number) {
        if (from === to || to < 0 || to >= files.length) {
            return;
        }

        const next = [...files];
        const [moved] = next.splice(from, 1);
        next.splice(to, 0, moved);
        onChange(next);
    }

    function append(incoming: FileList | null) {
        if (!incoming || incoming.length === 0) {
            return;
        }

        const seen = new Set(files.map(fileKey));
        const next = [...files];

        for (const file of Array.from(incoming)) {
            if (next.length >= max) {
                break;
            }

            const key = fileKey(file);

            if (seen.has(key)) {
                continue;
            }

            seen.add(key);
            next.push(file);
        }

        onChange(next);
    }

    function reorderDrop(event: DragEvent<HTMLLIElement>, index: number) {
        event.preventDefault();
        event.stopPropagation();

        if (dragIndex !== null) {
            move(dragIndex, index);
        }

        setDragIndex(null);
    }

    function fileDrop(event: DragEvent<HTMLDivElement>) {
        event.preventDefault();

        if (event.dataTransfer.files.length > 0) {
            append(event.dataTransfer.files);
        }
    }

    return (
        <div
            onDragOver={(event) => event.preventDefault()}
            onDrop={fileDrop}
            className="space-y-3"
        >
            {previews.length > 0 ? (
                <ul className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
                    {previews.map((url, index) => (
                        <li
                            key={url}
                            draggable
                            onDragStart={() => setDragIndex(index)}
                            onDragOver={(event) => event.preventDefault()}
                            onDrop={(event) => reorderDrop(event, index)}
                            onDragEnd={() => setDragIndex(null)}
                            className={cn(
                                'group relative aspect-square cursor-grab overflow-hidden border border-border-default bg-neutral-secondary-medium active:cursor-grabbing',
                                dragIndex === index && 'opacity-40',
                            )}
                        >
                            <img
                                src={url}
                                alt=""
                                className="h-full w-full object-cover"
                            />
                            <span className="absolute top-1.5 left-1.5 flex h-6 min-w-6 items-center justify-center bg-brand px-1 text-xs font-semibold text-on-brand dark:text-neutral-primary">
                                {index === 0
                                    ? t('auctions.form.screenshots_primary')
                                    : index + 1}
                            </span>
                            <button
                                type="button"
                                onClick={() =>
                                    onChange(
                                        files.filter((_, i) => i !== index),
                                    )
                                }
                                aria-label={t(
                                    'auctions.form.screenshots_remove',
                                )}
                                className="absolute top-1.5 right-1.5 flex h-6 w-6 items-center justify-center bg-danger text-sm leading-none text-neutral-primary"
                            >
                                ×
                            </button>
                            <div className="absolute inset-x-0 bottom-0 flex items-center justify-between border-t border-border-default bg-neutral-primary-soft/95 px-1 py-0.5 opacity-0 transition-opacity group-focus-within:opacity-100 group-hover:opacity-100">
                                <button
                                    type="button"
                                    onClick={() => move(index, index - 1)}
                                    disabled={index === 0}
                                    aria-label={t(
                                        'auctions.form.screenshots_move_left',
                                    )}
                                    className="px-2 text-base text-fg-brand disabled:opacity-30"
                                >
                                    ‹
                                </button>
                                <button
                                    type="button"
                                    onClick={() => move(index, index + 1)}
                                    disabled={index === files.length - 1}
                                    aria-label={t(
                                        'auctions.form.screenshots_move_right',
                                    )}
                                    className="px-2 text-base text-fg-brand disabled:opacity-30"
                                >
                                    ›
                                </button>
                            </div>
                        </li>
                    ))}
                </ul>
            ) : null}

            <div className="flex flex-wrap items-center gap-3">
                <button
                    type="button"
                    onClick={() => inputRef.current?.click()}
                    disabled={remaining <= 0}
                    className="inline-flex items-center gap-2 border border-dashed border-border-default-strong px-4 py-2.5 text-sm font-medium text-fg-brand transition-colors hover:bg-neutral-secondary-medium disabled:cursor-not-allowed disabled:text-fg-disabled"
                >
                    {t('auctions.form.screenshots_add')}
                </button>
                <span className="text-xs text-body-subtle">
                    {t('auctions.form.screenshots_count', {
                        count: files.length,
                        max,
                    })}
                    {previews.length > 1
                        ? ` · ${t('auctions.form.screenshots_drag')}`
                        : ''}
                </span>
            </div>

            <input
                ref={inputRef}
                id={id}
                type="file"
                multiple
                accept="image/png,image/jpeg,image/webp"
                className="hidden"
                onChange={(event) => {
                    append(event.target.files);
                    event.target.value = '';
                }}
            />
        </div>
    );
}

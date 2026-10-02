import { Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { NumberInput } from '@/components/number-input';
import { ScreenshotManager } from '@/components/screenshot-manager';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { InputError } from '@/components/ui/input-error';
import { TextInput } from '@/components/ui/text-input';
import { Textarea } from '@/components/ui/textarea';
import { AppLayout } from '@/layouts/app-layout';
import { statusVariant } from '@/lib/auction';
import { formatCurrency } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';
import { useFilePreviews } from '@/lib/use-file-previews';
import { cn } from '@/lib/utils';
import {
    index as auctionsIndex,
    store as auctionsStore,
} from '@/routes/auctions';

type CreateProps = {
    minimumStartingPrice: number;
    minimumStartingPriceLabel: string;
    maximumScreenshots: number;
};

export default function AuctionsCreate({
    minimumStartingPriceLabel,
    maximumScreenshots,
}: CreateProps) {
    const { t } = useTranslation();
    const { locale, currency } = usePage().props;

    const { data, setData, post, processing, progress, errors } = useForm<{
        title: string;
        description: string;
        starting_price: string;
        reserve_price: string;
        screenshots: File[];
    }>({
        title: '',
        description: '',
        starting_price: '',
        reserve_price: '',
        screenshots: [],
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        post(auctionsStore.url(), { forceFormData: true });
    }

    const screenshotError =
        errors.screenshots ??
        Object.entries(errors).find(([key]) =>
            key.startsWith('screenshots.'),
        )?.[1];

    // The preview mirrors the real listing card, so the seller sees exactly
    // what buyers will get. It shares the manager's stable object URLs so the
    // thumbnail never flickers while the seller types.
    const previewImages = useFilePreviews(data.screenshots);

    const previewPrice = formatCurrency(data.starting_price, locale, currency);

    return (
        <AppLayout title={t('auctions.create.title')}>
            <div className="mx-auto w-full max-w-6xl px-6 py-12">
                <h1 className="font-heading text-2xl font-bold text-heading">
                    {t('auctions.create.title')}
                </h1>
                <p className="mt-2 text-sm text-body-subtle">
                    {t('auctions.create.subtitle')}
                </p>

                <form
                    onSubmit={submit}
                    className="mt-8 grid items-start gap-6 lg:grid-cols-3"
                >
                    <div className="space-y-6 lg:col-span-2">
                        <section className="border border-border-default bg-neutral-primary-soft p-6">
                            <h2 className="font-heading text-lg font-bold text-heading">
                                {t('auctions.create.section_details')}
                            </h2>

                            <div className="mt-5 space-y-5">
                                <div>
                                    <label
                                        htmlFor="title"
                                        className="mb-2 block text-sm font-medium text-heading"
                                    >
                                        {t('auctions.form.title')}
                                    </label>
                                    <TextInput
                                        id="title"
                                        name="title"
                                        value={data.title}
                                        invalid={Boolean(errors.title)}
                                        onChange={(event) =>
                                            setData('title', event.target.value)
                                        }
                                        placeholder={t(
                                            'auctions.form.title_placeholder',
                                        )}
                                    />
                                    <InputError message={errors.title} />
                                </div>

                                <div>
                                    <label
                                        htmlFor="description"
                                        className="mb-2 block text-sm font-medium text-heading"
                                    >
                                        {t('auctions.form.description')}
                                    </label>
                                    <Textarea
                                        id="description"
                                        name="description"
                                        rows={7}
                                        value={data.description}
                                        invalid={Boolean(errors.description)}
                                        onChange={(event) =>
                                            setData(
                                                'description',
                                                event.target.value,
                                            )
                                        }
                                        placeholder={t(
                                            'auctions.form.description_placeholder',
                                        )}
                                    />
                                    <InputError message={errors.description} />
                                </div>
                            </div>
                        </section>

                        <section className="border border-border-default bg-neutral-primary-soft p-6">
                            <div className="flex flex-wrap items-baseline justify-between gap-2">
                                <h2 className="font-heading text-lg font-bold text-heading">
                                    {t('auctions.form.screenshots')}
                                </h2>
                                <span className="text-xs text-body-subtle">
                                    {t('auctions.form.screenshots_hint', {
                                        count: maximumScreenshots,
                                    })}
                                </span>
                            </div>

                            <div className="mt-5">
                                <ScreenshotManager
                                    id="screenshots"
                                    files={data.screenshots}
                                    onChange={(files) =>
                                        setData('screenshots', files)
                                    }
                                    max={maximumScreenshots}
                                />
                                <InputError message={screenshotError} />
                            </div>
                        </section>
                    </div>

                    <div className="space-y-6 lg:sticky lg:top-6">
                        <section className="border border-border-default bg-neutral-primary-soft p-6">
                            <h2 className="font-heading text-lg font-bold text-heading">
                                {t('auctions.create.section_price')}
                            </h2>

                            <div className="mt-5">
                                <label
                                    htmlFor="starting_price"
                                    className="mb-2 block text-sm font-medium text-heading"
                                >
                                    {t('auctions.form.starting_price')}
                                </label>
                                <NumberInput
                                    id="starting_price"
                                    name="starting_price"
                                    value={data.starting_price}
                                    invalid={Boolean(errors.starting_price)}
                                    onValueChange={(value) =>
                                        setData('starting_price', value)
                                    }
                                />
                                <p className="mt-1 text-xs text-body-subtle">
                                    {t('auctions.form.starting_price_hint', {
                                        amount: minimumStartingPriceLabel,
                                    })}
                                </p>
                                <InputError message={errors.starting_price} />
                            </div>

                            <div className="mt-5">
                                <label
                                    htmlFor="reserve_price"
                                    className="mb-2 block text-sm font-medium text-heading"
                                >
                                    {t('auctions.form.reserve_price')}
                                </label>
                                <NumberInput
                                    id="reserve_price"
                                    name="reserve_price"
                                    value={data.reserve_price}
                                    invalid={Boolean(errors.reserve_price)}
                                    onValueChange={(value) =>
                                        setData('reserve_price', value)
                                    }
                                />
                                <p className="mt-1 text-xs text-body-subtle">
                                    {t('auctions.form.reserve_price_hint')}
                                </p>
                                <InputError message={errors.reserve_price} />
                            </div>
                        </section>

                        <section className="border border-border-default bg-neutral-secondary-medium p-6">
                            <h2 className="text-xs font-medium tracking-wide text-body-subtle uppercase">
                                {t('auctions.create.preview')}
                            </h2>

                            <div className="mt-4 flex flex-col border border-border-default bg-neutral-primary-soft shadow-xs">
                                <div className="aspect-[16/10] w-full overflow-hidden bg-neutral-secondary-medium">
                                    {previewImages[0] !== undefined ? (
                                        <img
                                            src={previewImages[0]}
                                            alt=""
                                            className="h-full w-full object-cover"
                                        />
                                    ) : (
                                        <div className="flex h-full w-full items-center justify-center text-xs text-body-subtle">
                                            {t('auctions.create.preview_empty')}
                                        </div>
                                    )}
                                </div>
                                <div className="flex flex-1 flex-col gap-3 p-5">
                                    <Badge variant={statusVariant('draft')}>
                                        {t('auctions.status.draft')}
                                    </Badge>
                                    <h3
                                        className={cn(
                                            'font-heading text-lg font-bold',
                                            data.title
                                                ? 'text-heading'
                                                : 'text-body-subtle',
                                        )}
                                    >
                                        {data.title ||
                                            t(
                                                'auctions.form.title_placeholder',
                                            )}
                                    </h3>
                                    <div className="mt-auto">
                                        <p className="text-xs font-medium tracking-wide text-body-subtle uppercase">
                                            {t('auctions.index.starting_price')}
                                        </p>
                                        <p
                                            className={cn(
                                                'font-heading text-2xl font-bold',
                                                previewPrice
                                                    ? 'text-fg-gold'
                                                    : 'text-body-subtle',
                                            )}
                                        >
                                            {previewPrice ||
                                                t(
                                                    'auctions.create.preview_price_empty',
                                                )}
                                        </p>
                                    </div>
                                    <p className="text-xs text-body-subtle">
                                        {t('auctions.index.ends_at')}:{' '}
                                        {t(
                                            'auctions.create.preview_ends_empty',
                                        )}
                                    </p>
                                </div>
                            </div>
                        </section>

                        {progress ? (
                            <div className="h-1 w-full bg-neutral-secondary-medium">
                                <div
                                    className="h-1 bg-brand"
                                    style={{
                                        width: `${progress.percentage}%`,
                                    }}
                                />
                            </div>
                        ) : null}

                        <div className="flex items-center gap-4">
                            <Button type="submit" disabled={processing}>
                                {t('auctions.form.submit')}
                            </Button>
                            <Link
                                href={auctionsIndex.url()}
                                className="text-sm font-medium text-fg-brand underline underline-offset-4 hover:no-underline"
                            >
                                {t('auctions.form.cancel')}
                            </Link>
                        </div>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}

import { Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { NumberInput } from '@/components/number-input';
import { Button } from '@/components/ui/button';
import { InputError } from '@/components/ui/input-error';
import { TextInput } from '@/components/ui/text-input';
import { Textarea } from '@/components/ui/textarea';
import { AppLayout } from '@/layouts/app-layout';
import { useTranslation } from '@/lib/i18n';
import {
    index as auctionsIndex,
    update as auctionsUpdate,
} from '@/routes/auctions';
import type { AuctionDetail } from '@/types/auction';

type EditProps = {
    auction: AuctionDetail;
    minimumStartingPrice: number;
    minimumStartingPriceLabel: string;
};

export default function AuctionsEdit({
    auction,
    minimumStartingPriceLabel,
}: EditProps) {
    const { t } = useTranslation();

    const { data, setData, put, processing, errors } = useForm<{
        title: string;
        description: string;
        starting_price: string;
        reserve_price: string;
    }>({
        title: auction.title,
        description: auction.description,
        starting_price: String(auction.starting_price),
        reserve_price:
            auction.reserve_price === null ? '' : String(auction.reserve_price),
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        put(auctionsUpdate.url(auction.uuid));
    }

    return (
        <AppLayout title={t('auctions.edit.title')}>
            <div className="mx-auto w-full max-w-6xl px-6 py-12">
                <h1 className="font-heading text-2xl font-bold text-heading">
                    {t('auctions.edit.title')}
                </h1>
                <p className="mt-2 text-sm text-body-subtle">
                    {t('auctions.edit.subtitle')}
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

                        {auction.screenshots.length > 0 ? (
                            <section className="border border-border-default bg-neutral-primary-soft p-6">
                                <h2 className="font-heading text-lg font-bold text-heading">
                                    {t('auctions.form.screenshots')}
                                </h2>
                                <div className="mt-5 grid grid-cols-4 gap-3 sm:grid-cols-6">
                                    {auction.screenshots.map((screenshot) => (
                                        <div
                                            key={screenshot.position}
                                            className="aspect-square overflow-hidden border border-border-default bg-neutral-secondary-medium"
                                        >
                                            <img
                                                src={screenshot.url}
                                                alt=""
                                                className="h-full w-full object-cover"
                                            />
                                        </div>
                                    ))}
                                </div>
                            </section>
                        ) : null}
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

                        <div className="flex items-center gap-4">
                            <Button type="submit" disabled={processing}>
                                {t('auctions.form.save')}
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

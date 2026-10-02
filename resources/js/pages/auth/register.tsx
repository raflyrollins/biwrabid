import { Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { store as registerStore } from '@/actions/App/Http/Controllers/Auth/RegisterController';
import { Button } from '@/components/ui/button';
import { InputError } from '@/components/ui/input-error';
import { TextInput } from '@/components/ui/text-input';
import { AuthLayout } from '@/layouts/auth-layout';
import { useTranslation } from '@/lib/i18n';
import { login } from '@/routes';

export default function Register() {
    const { t } = useTranslation();

    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        post(registerStore.url(), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    }

    return (
        <AuthLayout
            title={t('auth.register.title')}
            subtitle={t('auth.register.subtitle')}
            illustration="/images/illustrations/mobile-log-in.svg"
        >
            <form onSubmit={submit} className="space-y-6">
                <div>
                    <label
                        htmlFor="name"
                        className="mb-2 block text-sm font-medium text-heading"
                    >
                        {t('auth.register.name')}
                    </label>
                    <TextInput
                        id="name"
                        type="text"
                        name="name"
                        value={data.name}
                        autoComplete="name"
                        autoFocus
                        onChange={(event) =>
                            setData('name', event.target.value)
                        }
                        invalid={Boolean(errors.name)}
                    />
                    <InputError message={errors.name} />
                </div>

                <div>
                    <label
                        htmlFor="email"
                        className="mb-2 block text-sm font-medium text-heading"
                    >
                        {t('auth.register.email')}
                    </label>
                    <TextInput
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        autoComplete="username"
                        onChange={(event) =>
                            setData('email', event.target.value)
                        }
                        invalid={Boolean(errors.email)}
                    />
                    <InputError message={errors.email} />
                </div>

                <div>
                    <label
                        htmlFor="password"
                        className="mb-2 block text-sm font-medium text-heading"
                    >
                        {t('auth.register.password')}
                    </label>
                    <TextInput
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        autoComplete="new-password"
                        onChange={(event) =>
                            setData('password', event.target.value)
                        }
                        invalid={Boolean(errors.password)}
                    />
                    <InputError message={errors.password} />
                </div>

                <div>
                    <label
                        htmlFor="password_confirmation"
                        className="mb-2 block text-sm font-medium text-heading"
                    >
                        {t('auth.register.password_confirmation')}
                    </label>
                    <TextInput
                        id="password_confirmation"
                        type="password"
                        name="password_confirmation"
                        value={data.password_confirmation}
                        autoComplete="new-password"
                        onChange={(event) =>
                            setData('password_confirmation', event.target.value)
                        }
                    />
                </div>

                <Button type="submit" disabled={processing} className="w-full">
                    {t('auth.register.submit')}
                </Button>
            </form>

            <p className="mt-6 text-center text-sm text-body-subtle">
                {t('auth.register.prompt')}{' '}
                <Link
                    href={login.url()}
                    className="font-medium text-fg-brand underline underline-offset-4 hover:no-underline"
                >
                    {t('auth.register.action')}
                </Link>
            </p>
        </AuthLayout>
    );
}

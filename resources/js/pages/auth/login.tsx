import { Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { store as loginStore } from '@/actions/App/Http/Controllers/Auth/LoginController';
import { Button } from '@/components/ui/button';
import { InputError } from '@/components/ui/input-error';
import { TextInput } from '@/components/ui/text-input';
import { AuthLayout } from '@/layouts/auth-layout';
import { useTranslation } from '@/lib/i18n';
import { register } from '@/routes';

type LoginProps = {
    status?: string | null;
};

export default function Login({ status }: LoginProps) {
    const { t } = useTranslation();

    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        post(loginStore.url(), {
            onFinish: () => reset('password'),
        });
    }

    return (
        <AuthLayout
            title={t('auth.login.title')}
            subtitle={t('auth.login.subtitle')}
        >
            {status ? (
                <p className="mb-6 text-sm text-fg-success">{status}</p>
            ) : null}

            <form onSubmit={submit} className="space-y-6">
                <div>
                    <label
                        htmlFor="email"
                        className="mb-2 block text-sm font-medium text-heading"
                    >
                        {t('auth.login.email')}
                    </label>
                    <TextInput
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        autoComplete="username"
                        autoFocus
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
                        {t('auth.login.password')}
                    </label>
                    <TextInput
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        autoComplete="current-password"
                        onChange={(event) =>
                            setData('password', event.target.value)
                        }
                        invalid={Boolean(errors.password)}
                    />
                    <InputError message={errors.password} />
                </div>

                <label className="flex items-center gap-3 text-sm text-body">
                    <input
                        type="checkbox"
                        name="remember"
                        checked={data.remember}
                        onChange={(event) =>
                            setData('remember', event.target.checked)
                        }
                        className="h-4 w-4 accent-brand"
                    />
                    {t('auth.login.remember')}
                </label>

                <Button type="submit" disabled={processing} className="w-full">
                    {t('auth.login.submit')}
                </Button>
            </form>

            <p className="mt-6 text-center text-sm text-body-subtle">
                {t('auth.login.prompt')}{' '}
                <Link
                    href={register.url()}
                    className="font-medium text-fg-brand underline underline-offset-4 hover:no-underline"
                >
                    {t('auth.login.action')}
                </Link>
            </p>
        </AuthLayout>
    );
}

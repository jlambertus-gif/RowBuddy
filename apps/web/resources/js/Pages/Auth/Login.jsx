import { Link, useForm, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AuthLayout from '../../Layouts/AuthLayout';

export default function Login() {
    const { t } = useTranslation('auth');
    const { flash } = usePage().props;

    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    function submit(event) {
        event.preventDefault();

        post('/login', {
            onFinish: () => reset('password'),
        });
    }

    return (
        <AuthLayout
            title={t('login.title')}
            subtitle={t('login.subtitle')}
        >
            {flash?.status && (
                <div className="mb-5 rounded-lg border border-emerald-800 bg-emerald-950 p-3 text-sm text-emerald-300">
                    {flash.status}
                </div>
            )}

            <form onSubmit={submit} className="space-y-5">
                <div>
                    <label
                        htmlFor="email"
                        className="mb-2 block text-sm font-medium"
                    >
                        {t('login.email_label')}
                    </label>

                    <input
                        id="email"
                        type="email"
                        value={data.email}
                        onChange={(event) =>
                            setData('email', event.target.value)
                        }
                        autoComplete="email"
                        autoFocus
                        required
                        className="w-full rounded-lg border border-slate-700 bg-slate-950 px-4 py-3 outline-none transition focus:border-sky-500"
                    />

                    {errors.email && (
                        <p className="mt-2 text-sm text-red-400">
                            {errors.email}
                        </p>
                    )}
                </div>

                <div>
                    <div className="mb-2 flex items-center justify-between">
                        <label
                            htmlFor="password"
                            className="text-sm font-medium"
                        >
                            {t('login.password_label')}
                        </label>

                        <Link
                            href="/forgot-password"
                            className="text-sm text-sky-400 hover:text-sky-300"
                        >
                            {t('login.forgot_password_link')}
                        </Link>
                    </div>

                    <input
                        id="password"
                        type="password"
                        value={data.password}
                        onChange={(event) =>
                            setData('password', event.target.value)
                        }
                        autoComplete="current-password"
                        required
                        className="w-full rounded-lg border border-slate-700 bg-slate-950 px-4 py-3 outline-none transition focus:border-sky-500"
                    />

                    {errors.password && (
                        <p className="mt-2 text-sm text-red-400">
                            {errors.password}
                        </p>
                    )}
                </div>

                <label className="flex items-center gap-3 text-sm text-slate-300">
                    <input
                        type="checkbox"
                        checked={data.remember}
                        onChange={(event) =>
                            setData('remember', event.target.checked)
                        }
                        className="h-4 w-4 rounded border-slate-700"
                    />

                    {t('login.remember_me')}
                </label>

                <button
                    type="submit"
                    disabled={processing}
                    className="w-full rounded-lg bg-sky-600 px-4 py-3 font-semibold text-white transition hover:bg-sky-500 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    {processing ? t('login.submitting') : t('login.submit_button')}
                </button>
            </form>

            <p className="mt-6 text-center text-sm text-slate-400">
                {t('login.no_account_prompt')}{' '}
                <Link
                    href="/register"
                    className="font-medium text-sky-400 hover:text-sky-300"
                >
                    {t('login.register_link')}
                </Link>
            </p>
        </AuthLayout>
    );
}

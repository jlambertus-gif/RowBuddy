import { useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AuthLayout from '../../Layouts/AuthLayout';

export default function ResetPassword({ email, token }) {
    const { t } = useTranslation('auth');
    // Threads a mobile-originated request's return marker from this
    // page's own URL through to the POST body untouched (ADR-028
    // Decision 7). Empty for every ordinary web user — the backend
    // treats an empty/missing value as "not mobile-originated" and
    // falls back to the existing behavior unchanged.
    const mobileReturn =
        new URLSearchParams(window.location.search).get('mobile_return') ??
        '';
    const { data, setData, post, processing, errors, reset } = useForm({
        token,
        email: email ?? '',
        password: '',
        password_confirmation: '',
        mobile_return: mobileReturn,
    });

    function submit(event) {
        event.preventDefault();

        post('/reset-password', {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    }

    return (
        <AuthLayout
            title={t('reset_password.title')}
            subtitle={t('reset_password.subtitle')}
        >
            <form onSubmit={submit} className="space-y-5">
                <div>
                    <label
                        htmlFor="email"
                        className="mb-2 block text-sm font-medium"
                    >
                        {t('reset_password.email_label')}
                    </label>

                    <input
                        id="email"
                        type="email"
                        value={data.email}
                        onChange={(event) =>
                            setData('email', event.target.value)
                        }
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
                    <label
                        htmlFor="password"
                        className="mb-2 block text-sm font-medium"
                    >
                        {t('reset_password.new_password_label')}
                    </label>

                    <input
                        id="password"
                        type="password"
                        value={data.password}
                        onChange={(event) =>
                            setData('password', event.target.value)
                        }
                        autoComplete="new-password"
                        required
                        className="w-full rounded-lg border border-slate-700 bg-slate-950 px-4 py-3 outline-none transition focus:border-sky-500"
                    />

                    {errors.password && (
                        <p className="mt-2 text-sm text-red-400">
                            {errors.password}
                        </p>
                    )}
                </div>

                <div>
                    <label
                        htmlFor="password_confirmation"
                        className="mb-2 block text-sm font-medium"
                    >
                        {t('reset_password.password_confirmation_label')}
                    </label>

                    <input
                        id="password_confirmation"
                        type="password"
                        value={data.password_confirmation}
                        onChange={(event) =>
                            setData(
                                'password_confirmation',
                                event.target.value,
                            )
                        }
                        autoComplete="new-password"
                        required
                        className="w-full rounded-lg border border-slate-700 bg-slate-950 px-4 py-3 outline-none transition focus:border-sky-500"
                    />
                </div>

                <button
                    type="submit"
                    disabled={processing}
                    className="w-full rounded-lg bg-sky-600 px-4 py-3 font-semibold text-white transition hover:bg-sky-500 disabled:opacity-50"
                >
                    {processing
                        ? t('reset_password.submitting')
                        : t('reset_password.submit_button')}
                </button>
            </form>
        </AuthLayout>
    );
}

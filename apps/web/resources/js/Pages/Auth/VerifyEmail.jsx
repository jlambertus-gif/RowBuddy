import { Link, useForm, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AuthLayout from '../../Layouts/AuthLayout';

export default function VerifyEmail() {
    const { t } = useTranslation('auth');
    const { flash } = usePage().props;

    const { post, processing } = useForm({});

    function resend(event) {
        event.preventDefault();
        post('/email/verification-notification');
    }

    return (
        <AuthLayout title={t('verify_email.title')}>
            {flash?.status === 'verification-link-sent' && (
                <div className="mb-5 rounded-lg border border-emerald-800 bg-emerald-950 p-3 text-sm text-emerald-300">
                    {t('verify_email.resend_sent')}
                </div>
            )}

            <p className="text-sm text-slate-300">{t('verify_email.body')}</p>

            <form onSubmit={resend} className="mt-6">
                <button
                    type="submit"
                    disabled={processing}
                    className="w-full rounded-lg bg-sky-600 px-4 py-3 font-semibold text-white transition hover:bg-sky-500 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    {t('verify_email.resend_button')}
                </button>
            </form>

            <Link
                href="/logout"
                method="post"
                as="button"
                className="mt-4 w-full rounded-lg border border-slate-700 px-4 py-3 text-center font-medium transition hover:bg-slate-800"
            >
                {t('verify_email.logout_button')}
            </Link>
        </AuthLayout>
    );
}

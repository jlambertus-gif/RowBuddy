import { Head, Link, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

export default function Dashboard() {
    const { auth } = usePage().props;
    const user = auth?.user;
    const { t } = useTranslation('queues');
    const { t: tCommon } = useTranslation('common');

    return (
        <>
            <Head title="Dashboard" />

            <main className="min-h-screen bg-slate-950 text-slate-100">
                <header className="border-b border-slate-800 bg-slate-900">
                    <div className="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
                        <div>
                            <h1 className="text-xl font-bold">RowBuddy</h1>
                            <p className="text-sm text-slate-400">
                                {tCommon('dashboard.panel_title')}
                            </p>
                        </div>

                        <Link
                            href="/logout"
                            method="post"
                            as="button"
                            className="rounded-lg border border-slate-700 px-4 py-2 text-sm font-medium transition hover:bg-slate-800"
                        >
                            {tCommon('nav.logout')}
                        </Link>
                    </div>
                </header>

                <section className="mx-auto max-w-6xl px-6 py-10">
                    <div className="rounded-2xl border border-slate-800 bg-slate-900 p-8">
                        <p className="text-sm font-medium uppercase tracking-wide text-sky-400">
                            {tCommon('dashboard.session_active')}
                        </p>

                        <h2 className="mt-3 text-3xl font-bold">
                            {tCommon('dashboard.welcome', { name: user?.name })}
                        </h2>

                        <p className="mt-2 text-slate-400">
                            {user?.email}
                        </p>

                        <p className="mt-8 text-slate-300">
                            {tCommon('dashboard.auth_working')}
                        </p>

                        <div className="mt-8 flex flex-wrap gap-3 border-t border-slate-800 pt-6">
                            <Link
                                href="/queues/submit"
                                className="rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-sky-500"
                            >
                                {t('nav.submit')}
                            </Link>

                            <Link
                                href="/discover"
                                className="rounded-lg border border-slate-700 px-4 py-2 text-sm font-medium transition hover:bg-slate-800"
                            >
                                {t('nav.discover')}
                            </Link>

                            {user?.is_admin && (
                                <Link
                                    href="/admin/queues/moderation"
                                    className="rounded-lg border border-slate-700 px-4 py-2 text-sm font-medium transition hover:bg-slate-800"
                                >
                                    {t('nav.moderate')}
                                </Link>
                            )}
                        </div>
                    </div>
                </section>
            </main>
        </>
    );
}
import { Head, Link, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

export default function AppLayout({ title, children }) {
    const { auth } = usePage().props;
    const { t: tCommon } = useTranslation('common');
    const { t: tQueues } = useTranslation('queues');
    const { t: tDisputes } = useTranslation('disputes');
    const { t: tAudit } = useTranslation('audit');
    const user = auth?.user;
    const isAdmin = Boolean(user?.is_admin);
    const canReviewDisputes = Boolean(user?.can_review_disputes);
    const canViewAuditLog = Boolean(user?.can_view_audit_log);

    return (
        <>
            <Head title={title} />

            <main className="min-h-screen bg-slate-950 text-slate-100">
                <header className="border-b border-slate-800 bg-slate-900">
                    <div className="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-6 py-4">
                        <Link
                            href={user ? '/dashboard' : '/'}
                            className="text-xl font-bold"
                        >
                            RowBuddy
                        </Link>

                        <nav className="flex flex-wrap items-center gap-4 text-sm">
                            {user && (
                                <Link
                                    href="/dashboard"
                                    className="hover:text-sky-400"
                                >
                                    {tCommon('nav.dashboard')}
                                </Link>
                            )}

                            <Link href="/discover" className="hover:text-sky-400">
                                {tQueues('nav.discover')}
                            </Link>

                            {user && (
                                <Link
                                    href="/queues/submit"
                                    className="hover:text-sky-400"
                                >
                                    {tQueues('nav.submit')}
                                </Link>
                            )}

                            {isAdmin && (
                                <Link
                                    href="/admin/queues/moderation"
                                    className="hover:text-sky-400"
                                >
                                    {tQueues('nav.moderate')}
                                </Link>
                            )}

                            {canReviewDisputes && (
                                <Link
                                    href="/admin/disputes/review"
                                    className="hover:text-sky-400"
                                >
                                    {tDisputes('nav.review')}
                                </Link>
                            )}

                            {canViewAuditLog && (
                                <Link
                                    href="/admin/audit-log"
                                    className="hover:text-sky-400"
                                >
                                    {tAudit('nav.audit_log')}
                                </Link>
                            )}

                            {user ? (
                                <Link
                                    href="/logout"
                                    method="post"
                                    as="button"
                                    className="rounded-lg border border-slate-700 px-4 py-2 font-medium transition hover:bg-slate-800"
                                >
                                    {tCommon('nav.logout')}
                                </Link>
                            ) : (
                                <Link
                                    href="/login"
                                    className="rounded-lg border border-slate-700 px-4 py-2 font-medium transition hover:bg-slate-800"
                                >
                                    {tCommon('nav.login')}
                                </Link>
                            )}
                        </nav>
                    </div>
                </header>

                <section className="mx-auto max-w-6xl px-6 py-10">
                    {children}
                </section>
            </main>
        </>
    );
}

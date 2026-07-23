import { Head, Link } from '@inertiajs/react';

export default function AuthLayout({ title, subtitle, children }) {
    return (
        <>
            <Head title={title} />

            <main className="min-h-screen bg-slate-950 px-4 py-12 text-slate-100">
                <div className="mx-auto flex min-h-[calc(100vh-6rem)] max-w-md items-center">
                    <section className="w-full rounded-2xl border border-slate-800 bg-slate-900 p-8 shadow-2xl">
                        <div className="mb-8 text-center">
                            <Link
                                href="/"
                                className="text-3xl font-bold tracking-tight text-white"
                            >
                                RowBuddy
                            </Link>

                            <h1 className="mt-6 text-2xl font-semibold">
                                {title}
                            </h1>

                            {subtitle && (
                                <p className="mt-2 text-sm text-slate-400">
                                    {subtitle}
                                </p>
                            )}
                        </div>

                        {children}
                    </section>
                </div>
            </main>
        </>
    );
}
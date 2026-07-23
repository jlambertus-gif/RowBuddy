import { Head, Link, usePage } from '@inertiajs/react';

export default function Dashboard() {
    const { auth } = usePage().props;
    const user = auth?.user;

    return (
        <>
            <Head title="Dashboard" />

            <main className="min-h-screen bg-slate-950 text-slate-100">
                <header className="border-b border-slate-800 bg-slate-900">
                    <div className="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
                        <div>
                            <h1 className="text-xl font-bold">RowBuddy</h1>
                            <p className="text-sm text-slate-400">
                                Panel de control
                            </p>
                        </div>

                        <Link
                            href="/logout"
                            method="post"
                            as="button"
                            className="rounded-lg border border-slate-700 px-4 py-2 text-sm font-medium transition hover:bg-slate-800"
                        >
                            Cerrar sesión
                        </Link>
                    </div>
                </header>

                <section className="mx-auto max-w-6xl px-6 py-10">
                    <div className="rounded-2xl border border-slate-800 bg-slate-900 p-8">
                        <p className="text-sm font-medium uppercase tracking-wide text-sky-400">
                            Sesión iniciada
                        </p>

                        <h2 className="mt-3 text-3xl font-bold">
                            Bienvenido, {user?.name}
                        </h2>

                        <p className="mt-2 text-slate-400">
                            {user?.email}
                        </p>

                        <p className="mt-8 text-slate-300">
                            La autenticación base de RowBuddy está funcionando.
                        </p>
                    </div>
                </section>
            </main>
        </>
    );
}
import { Link, useForm, usePage } from '@inertiajs/react';
import AuthLayout from '../../Layouts/AuthLayout';

export default function ForgotPassword() {
    const { flash } = usePage().props;

    const { data, setData, post, processing, errors } = useForm({
        email: '',
    });

    function submit(event) {
        event.preventDefault();
        post('/forgot-password');
    }

    return (
        <AuthLayout
            title="Recuperar contraseña"
            subtitle="Te enviaremos un enlace para restablecerla"
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
                        Correo electrónico
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

                <button
                    type="submit"
                    disabled={processing}
                    className="w-full rounded-lg bg-sky-600 px-4 py-3 font-semibold text-white transition hover:bg-sky-500 disabled:opacity-50"
                >
                    {processing
                        ? 'Enviando enlace...'
                        : 'Enviar enlace de recuperación'}
                </button>
            </form>

            <p className="mt-6 text-center text-sm">
                <Link
                    href="/login"
                    className="text-sky-400 hover:text-sky-300"
                >
                    Volver al inicio de sesión
                </Link>
            </p>
        </AuthLayout>
    );
}
export default function Field({ id, label, error, children }) {
    return (
        <div>
            <label htmlFor={id} className="mb-2 block text-sm font-medium">
                {label}
            </label>

            {children}

            {error && <p className="mt-2 text-sm text-red-400">{error}</p>}
        </div>
    );
}

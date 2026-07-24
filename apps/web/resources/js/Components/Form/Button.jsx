export default function Button({ children, className = '', ...props }) {
    return (
        <button
            {...props}
            className={`rounded-lg bg-sky-600 px-4 py-3 font-semibold text-white transition hover:bg-sky-500 disabled:cursor-not-allowed disabled:opacity-50 ${className}`}
        >
            {children}
        </button>
    );
}

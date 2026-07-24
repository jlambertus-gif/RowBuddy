export default function TextInput({ className = '', ...props }) {
    return (
        <input
            {...props}
            className={`w-full rounded-lg border border-slate-700 bg-slate-950 px-4 py-3 outline-none transition focus:border-sky-500 ${className}`}
        />
    );
}

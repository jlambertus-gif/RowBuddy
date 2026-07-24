import { useTranslation } from 'react-i18next';

const COLORS = {
    pending: 'border-amber-800 bg-amber-950 text-amber-300',
    approved: 'border-sky-800 bg-sky-950 text-sky-300',
    published: 'border-emerald-800 bg-emerald-950 text-emerald-300',
    rejected: 'border-red-800 bg-red-950 text-red-300',
};

export default function StatusBadge({ status }) {
    const { t } = useTranslation('queues');

    return (
        <span
            className={`inline-block rounded-full border px-3 py-1 text-xs font-medium ${COLORS[status] ?? 'border-slate-700 bg-slate-800 text-slate-300'}`}
        >
            {t(`status.${status}`)}
        </span>
    );
}

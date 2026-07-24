import { useTranslation } from 'react-i18next';

export default function Pagination({ page, perPage, total, hasMore, onPageChange }) {
    const { t } = useTranslation('queues');
    const totalPages = Math.max(1, Math.ceil(total / perPage));

    return (
        <div className="mt-6 flex items-center justify-between text-sm text-slate-400">
            <button
                type="button"
                onClick={() => onPageChange(page - 1)}
                disabled={page <= 1}
                className="rounded-lg border border-slate-700 px-4 py-2 transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-40"
            >
                {t('discover.previous')}
            </button>

            <span>{t('discover.page_indicator', { page, totalPages })}</span>

            <button
                type="button"
                onClick={() => onPageChange(page + 1)}
                disabled={!hasMore}
                className="rounded-lg border border-slate-700 px-4 py-2 transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-40"
            >
                {t('discover.next')}
            </button>
        </div>
    );
}

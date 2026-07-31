import axios from 'axios';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import Button from '../../Components/Form/Button';
import AppLayout from '../../Layouts/AppLayout';

export default function DisputeReview() {
    const { t } = useTranslation('disputes');
    const [disputes, setDisputes] = useState([]);
    const [loading, setLoading] = useState(true);
    const [loadError, setLoadError] = useState(null);
    const [notes, setNotes] = useState({});
    const [actionErrors, setActionErrors] = useState({});
    const [actionMessages, setActionMessages] = useState({});
    const [busyId, setBusyId] = useState(null);

    useEffect(() => {
        let cancelled = false;

        axios
            .get('/admin/disputes')
            .then((response) => {
                if (!cancelled) {
                    setDisputes(response.data.data);
                }
            })
            .catch(() => {
                if (!cancelled) {
                    setLoadError(t('review.load_error'));
                }
            })
            .finally(() => {
                if (!cancelled) {
                    setLoading(false);
                }
            });

        return () => {
            cancelled = true;
        };
    }, [t]);

    async function recordCorrection(id) {
        const note = notes[id];

        if (!note) {
            setActionErrors((current) => ({ ...current, [id]: t('review.note_required') }));
            return;
        }

        setBusyId(id);
        setActionErrors((current) => ({ ...current, [id]: null }));
        setActionMessages((current) => ({ ...current, [id]: null }));

        try {
            const response = await axios.post(`/admin/disputes/${id}/corrections`, { note });
            setActionMessages((current) => ({ ...current, [id]: response.data.message }));
            setNotes((current) => ({ ...current, [id]: '' }));
        } catch (error) {
            setActionErrors((current) => ({ ...current, [id]: error.response?.data?.message }));
        } finally {
            setBusyId(null);
        }
    }

    return (
        <AppLayout title={t('review.title')}>
            <h1 className="text-2xl font-bold">{t('review.title')}</h1>
            <p className="mt-2 text-slate-400">{t('review.subtitle')}</p>

            {loading && <p className="mt-6 text-slate-400">{t('review.loading')}</p>}

            {loadError && <p className="mt-6 text-red-400">{loadError}</p>}

            {!loading && !loadError && disputes.length === 0 && (
                <p className="mt-6 text-slate-400">{t('review.empty')}</p>
            )}

            <div className="mt-6 grid gap-4">
                {disputes.map((dispute) => (
                    <div
                        key={dispute.id}
                        className="rounded-xl border border-slate-800 bg-slate-900 p-6"
                    >
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <span className="font-mono text-sm text-slate-400">
                                {dispute.id}
                            </span>
                            <span className="rounded-full border border-slate-700 px-3 py-1 text-xs uppercase tracking-wide">
                                {t(`review.status.${dispute.status}`)}
                            </span>
                        </div>

                        <p className="mt-3 text-slate-200">{dispute.reason}</p>

                        {dispute.status === 'resolved' && (
                            <div className="mt-3 rounded-lg bg-slate-950 p-4 text-sm text-slate-400">
                                <p>
                                    {t('review.resolution_outcome')}:{' '}
                                    {dispute.resolution_outcome}
                                </p>
                                {dispute.refund_amount_minor_units !== null && (
                                    <p className="mt-1">
                                        {t('review.refund_amount')}:{' '}
                                        {(dispute.refund_amount_minor_units / 100).toFixed(2)}{' '}
                                        {dispute.refund_amount_currency}
                                    </p>
                                )}
                                {dispute.resolution_notes && (
                                    <p className="mt-1">
                                        {t('review.resolution_notes')}:{' '}
                                        {dispute.resolution_notes}
                                    </p>
                                )}
                            </div>
                        )}

                        {dispute.evidence.length > 0 && (
                            <div className="mt-3">
                                <p className="text-sm font-semibold text-slate-300">
                                    {t('review.evidence')}
                                </p>
                                <ul className="mt-1 space-y-1 text-sm text-slate-400">
                                    {dispute.evidence.map((evidence, index) => (
                                        <li key={index}>
                                            <span className="font-semibold text-slate-300">
                                                {t(`review.evidence_type.${evidence.type}`)}
                                            </span>{' '}
                                            — {evidence.submitted_at}
                                            {evidence.content && (
                                                <p className="mt-1 text-slate-300">
                                                    “{evidence.content}”
                                                </p>
                                            )}
                                            {evidence.type === 'photo' && (
                                                <p className="mt-1 italic text-slate-500">
                                                    {t('review.photo_not_viewable')}
                                                </p>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}

                        <div className="mt-4 space-y-3">
                            {actionErrors[dispute.id] && (
                                <p className="text-sm text-red-400">
                                    {actionErrors[dispute.id]}
                                </p>
                            )}

                            {actionMessages[dispute.id] && (
                                <p className="text-sm text-emerald-400">
                                    {actionMessages[dispute.id]}
                                </p>
                            )}

                            <textarea
                                className="w-full rounded-lg border border-slate-700 bg-slate-950 px-4 py-3 outline-none transition focus:border-sky-500"
                                rows={2}
                                placeholder={t('review.note_placeholder')}
                                value={notes[dispute.id] ?? ''}
                                onChange={(event) =>
                                    setNotes((current) => ({
                                        ...current,
                                        [dispute.id]: event.target.value,
                                    }))
                                }
                            />

                            <Button
                                type="button"
                                disabled={busyId === dispute.id}
                                onClick={() => recordCorrection(dispute.id)}
                            >
                                {t('review.record_correction')}
                            </Button>
                        </div>
                    </div>
                ))}
            </div>
        </AppLayout>
    );
}

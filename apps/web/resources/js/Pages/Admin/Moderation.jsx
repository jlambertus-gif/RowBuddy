import axios from 'axios';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import Button from '../../Components/Form/Button';
import TextInput from '../../Components/Form/TextInput';
import QueueCard from '../../Components/Queues/QueueCard';
import AppLayout from '../../Layouts/AppLayout';

export default function Moderation() {
    const { t } = useTranslation('queues');
    const [queues, setQueues] = useState([]);
    const [loading, setLoading] = useState(true);
    const [loadError, setLoadError] = useState(null);
    const [reasons, setReasons] = useState({});
    const [actionErrors, setActionErrors] = useState({});
    const [busyId, setBusyId] = useState(null);

    useEffect(() => {
        let cancelled = false;

        axios
            .get('/admin/queues')
            .then((response) => {
                if (!cancelled) {
                    setQueues(response.data.data);
                }
            })
            .catch(() => {
                if (!cancelled) {
                    setLoadError(t('moderation.load_error'));
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

    function updateQueue(id, updated) {
        setQueues((current) => current.map((queue) => (queue.id === id ? updated : queue)));
    }

    function removeQueue(id) {
        setQueues((current) => current.filter((queue) => queue.id !== id));
    }

    function setActionError(id, message) {
        setActionErrors((current) => ({ ...current, [id]: message }));
    }

    async function approve(id) {
        setBusyId(id);
        setActionError(id, null);

        try {
            const response = await axios.post(`/admin/queues/${id}/approve`);
            updateQueue(id, response.data.data);
        } catch (error) {
            setActionError(id, error.response?.data?.message);
        } finally {
            setBusyId(null);
        }
    }

    async function reject(id) {
        const reason = reasons[id];

        if (!reason) {
            setActionError(id, t('moderation.reason_required'));
            return;
        }

        setBusyId(id);
        setActionError(id, null);

        try {
            await axios.post(`/admin/queues/${id}/reject`, { reason });
            removeQueue(id);
        } catch (error) {
            setActionError(id, error.response?.data?.message);
        } finally {
            setBusyId(null);
        }
    }

    async function publish(id) {
        setBusyId(id);
        setActionError(id, null);

        try {
            await axios.post(`/admin/queues/${id}/publish`);
            removeQueue(id);
        } catch (error) {
            setActionError(id, error.response?.data?.message);
        } finally {
            setBusyId(null);
        }
    }

    return (
        <AppLayout title={t('moderation.title')}>
            <h1 className="text-2xl font-bold">{t('moderation.title')}</h1>
            <p className="mt-2 text-slate-400">{t('moderation.subtitle')}</p>

            {loading && (
                <p className="mt-6 text-slate-400">{t('moderation.loading')}</p>
            )}

            {loadError && <p className="mt-6 text-red-400">{loadError}</p>}

            {!loading && !loadError && queues.length === 0 && (
                <p className="mt-6 text-slate-400">{t('moderation.empty')}</p>
            )}

            <div className="mt-6 grid gap-4">
                {queues.map((queue) => (
                    <QueueCard
                        key={queue.id}
                        queue={queue}
                        actions={
                            <div className="space-y-3">
                                {actionErrors[queue.id] && (
                                    <p className="text-sm text-red-400">
                                        {actionErrors[queue.id]}
                                    </p>
                                )}

                                {queue.status === 'pending' && (
                                    <div className="flex flex-wrap items-center gap-3">
                                        <Button
                                            type="button"
                                            disabled={busyId === queue.id}
                                            onClick={() => approve(queue.id)}
                                        >
                                            {t('moderation.approve')}
                                        </Button>

                                        <TextInput
                                            type="text"
                                            placeholder={t(
                                                'moderation.reason_placeholder',
                                            )}
                                            value={reasons[queue.id] ?? ''}
                                            onChange={(event) =>
                                                setReasons((current) => ({
                                                    ...current,
                                                    [queue.id]: event.target.value,
                                                }))
                                            }
                                            className="max-w-xs"
                                        />

                                        <Button
                                            type="button"
                                            disabled={busyId === queue.id}
                                            onClick={() => reject(queue.id)}
                                            className="bg-red-700 hover:bg-red-600"
                                        >
                                            {t('moderation.reject')}
                                        </Button>
                                    </div>
                                )}

                                {queue.status === 'approved' && (
                                    <Button
                                        type="button"
                                        disabled={busyId === queue.id}
                                        onClick={() => publish(queue.id)}
                                    >
                                        {t('moderation.publish')}
                                    </Button>
                                )}
                            </div>
                        }
                    />
                ))}
            </div>
        </AppLayout>
    );
}

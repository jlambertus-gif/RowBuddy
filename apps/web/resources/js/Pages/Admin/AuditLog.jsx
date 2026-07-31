import axios from 'axios';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import AppLayout from '../../Layouts/AppLayout';

export default function AuditLog() {
    const { t } = useTranslation('audit');
    const [events, setEvents] = useState([]);
    const [loading, setLoading] = useState(true);
    const [loadError, setLoadError] = useState(null);

    useEffect(() => {
        let cancelled = false;

        axios
            .get('/admin/audit-events')
            .then((response) => {
                if (!cancelled) {
                    setEvents(response.data.data);
                }
            })
            .catch(() => {
                if (!cancelled) {
                    setLoadError(t('log.load_error'));
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

    return (
        <AppLayout title={t('log.title')}>
            <h1 className="text-2xl font-bold">{t('log.title')}</h1>
            <p className="mt-2 text-slate-400">{t('log.subtitle')}</p>

            {loading && <p className="mt-6 text-slate-400">{t('log.loading')}</p>}

            {loadError && <p className="mt-6 text-red-400">{loadError}</p>}

            {!loading && !loadError && events.length === 0 && (
                <p className="mt-6 text-slate-400">{t('log.empty')}</p>
            )}

            <div className="mt-6 grid gap-3">
                {events.map((event) => (
                    <div
                        key={event.id}
                        className="rounded-xl border border-slate-800 bg-slate-900 p-4"
                    >
                        <div className="flex flex-wrap items-center justify-between gap-2 text-sm">
                            <span className="font-mono text-slate-300">
                                {event.event_name}
                            </span>
                            <span className="text-slate-500">
                                {event.occurred_at}
                            </span>
                        </div>

                        <p className="mt-1 text-xs text-slate-500">
                            {event.subject_type} — {event.subject_id}
                        </p>

                        <ul className="mt-2 space-y-1 text-sm text-slate-400">
                            {Object.entries(event.fields).map(([key, value]) => (
                                <li key={key}>
                                    <span className="text-slate-300">{key}</span>: {String(value)}
                                </li>
                            ))}
                        </ul>
                    </div>
                ))}
            </div>
        </AppLayout>
    );
}

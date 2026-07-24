import { useTranslation } from 'react-i18next';
import StatusBadge from './StatusBadge';

export default function QueueCard({ queue, actions }) {
    const { t } = useTranslation('queues');

    return (
        <div className="rounded-2xl border border-slate-800 bg-slate-900 p-6">
            <div className="flex items-start justify-between gap-4">
                <div>
                    <p className="text-lg font-semibold capitalize">
                        {queue.category}
                    </p>
                    <p className="text-sm text-slate-400">
                        {queue.jurisdiction_country}
                    </p>
                </div>

                <StatusBadge status={queue.status} />
            </div>

            <dl className="mt-4 grid grid-cols-2 gap-3 text-sm text-slate-300">
                <div>
                    <dt className="text-slate-500">{t('fields.latitude')}</dt>
                    <dd>{queue.geofence.latitude}</dd>
                </div>
                <div>
                    <dt className="text-slate-500">{t('fields.longitude')}</dt>
                    <dd>{queue.geofence.longitude}</dd>
                </div>
                <div>
                    <dt className="text-slate-500">
                        {t('fields.radius_meters')}
                    </dt>
                    <dd>{queue.geofence.radius_meters}</dd>
                </div>
            </dl>

            {typeof queue.distance_meters === 'number' && (
                <p className="mt-3 text-sm font-medium text-sky-400">
                    {t('discover.distance_away', {
                        distance: Math.round(queue.distance_meters),
                    })}
                </p>
            )}

            {actions && <div className="mt-4">{actions}</div>}
        </div>
    );
}

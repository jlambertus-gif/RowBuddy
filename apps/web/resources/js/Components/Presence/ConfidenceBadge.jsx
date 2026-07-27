import { useTranslation } from 'react-i18next';

const COLORS = {
    unverified: 'border-slate-700 bg-slate-800 text-slate-300',
    location_verified: 'border-sky-800 bg-sky-950 text-sky-300',
    evidence_verified: 'border-emerald-800 bg-emerald-950 text-emerald-300',
    community_verified: 'border-emerald-800 bg-emerald-950 text-emerald-300',
    transfer_completed: 'border-emerald-800 bg-emerald-950 text-emerald-300',
};

export default function ConfidenceBadge({ tier, points }) {
    const { t } = useTranslation('presence');

    return (
        <span
            className={`inline-block rounded-full border px-3 py-1 text-xs font-medium ${COLORS[tier] ?? COLORS.unverified}`}
        >
            {t(`tier.${tier}`)} · {points} {t('points_suffix')}
        </span>
    );
}

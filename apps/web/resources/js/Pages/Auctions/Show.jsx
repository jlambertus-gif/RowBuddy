import { usePage } from '@inertiajs/react';
import axios from 'axios';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import Button from '../../Components/Form/Button';
import Field from '../../Components/Form/Field';
import TextInput from '../../Components/Form/TextInput';
import AppLayout from '../../Layouts/AppLayout';
import { getEcho } from '../../echo';

export default function Show({ auctionId }) {
    const { t } = useTranslation('auctions');
    const { auth } = usePage().props;
    const [snapshot, setSnapshot] = useState(null);
    const [loadError, setLoadError] = useState(null);
    const [amount, setAmount] = useState('');
    const [placing, setPlacing] = useState(false);
    const [bidError, setBidError] = useState(null);

    useEffect(() => {
        let active = true;

        axios
            .get(`/auctions/${auctionId}`)
            .then((response) => {
                if (active) {
                    setSnapshot(response.data.data);
                }
            })
            .catch((error) => {
                if (active) {
                    setLoadError(
                        error.response?.data?.message ?? t('errors.load_error'),
                    );
                }
            });

        const echo = getEcho();
        const channel = echo.channel(`auctions.${auctionId}`);
        channel.listen('.snapshot.updated', (payload) => {
            if (active) {
                setSnapshot(payload);
            }
        });

        return () => {
            active = false;
            echo.leaveChannel(`auctions.${auctionId}`);
        };
    }, [auctionId]);

    function submitBid(event) {
        event.preventDefault();
        setBidError(null);
        setPlacing(true);

        axios
            .post(
                `/auctions/${auctionId}/bids`,
                {
                    amount_minor_units: Math.round(Number(amount) * 100),
                    currency: snapshot?.current_price?.currency ?? 'USD',
                },
                {
                    headers: { 'Idempotency-Key': crypto.randomUUID() },
                },
            )
            .then(() => {
                setAmount('');
            })
            .catch((error) => {
                setBidError(error.response?.data?.message ?? t('errors.generic'));
            })
            .finally(() => setPlacing(false));
    }

    if (loadError) {
        return (
            <AppLayout title={t('title')}>
                <div className="rounded-lg border border-red-800 bg-red-950 p-3 text-sm text-red-300">
                    {loadError}
                </div>
            </AppLayout>
        );
    }

    if (!snapshot) {
        return (
            <AppLayout title={t('title')}>
                <p className="text-slate-400">{t('loading')}</p>
            </AppLayout>
        );
    }

    const canBid = Boolean(auth?.user) && snapshot.status === 'open';
    const price = snapshot.current_price;

    return (
        <AppLayout title={t('title')}>
            <h1 className="text-2xl font-bold">{t('title')}</h1>

            <dl className="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div>
                    <dt className="text-sm text-slate-400">{t('fields.status')}</dt>
                    <dd className="text-lg font-semibold">{t(`status.${snapshot.status}`)}</dd>
                </div>
                <div>
                    <dt className="text-sm text-slate-400">{t('fields.current_price')}</dt>
                    <dd className="text-lg font-semibold">
                        {(price.amount_minor_units / 100).toFixed(2)} {price.currency}
                    </dd>
                </div>
                <div>
                    <dt className="text-sm text-slate-400">{t('fields.bid_count')}</dt>
                    <dd className="text-lg font-semibold">{snapshot.bid_count}</dd>
                </div>
                <div>
                    <dt className="text-sm text-slate-400">{t('fields.closes_at')}</dt>
                    <dd className="text-lg font-semibold">
                        {new Date(snapshot.closes_at).toLocaleString()}
                    </dd>
                </div>
            </dl>

            {canBid && (
                <form onSubmit={submitBid} className="mt-8 max-w-sm">
                    <Field id="amount" label={t('fields.bid_amount')}>
                        <TextInput
                            id="amount"
                            type="number"
                            step="0.01"
                            min="0"
                            value={amount}
                            onChange={(event) => setAmount(event.target.value)}
                            required
                        />
                    </Field>

                    {bidError && (
                        <div className="mt-2 rounded-lg border border-red-800 bg-red-950 p-3 text-sm text-red-300">
                            {bidError}
                        </div>
                    )}

                    <Button type="submit" disabled={placing} className="mt-4">
                        {placing ? t('actions.placing') : t('actions.place_bid')}
                    </Button>
                </form>
            )}

            {!auth?.user && (
                <p className="mt-8 text-slate-400">{t('errors.login_required')}</p>
            )}
        </AppLayout>
    );
}

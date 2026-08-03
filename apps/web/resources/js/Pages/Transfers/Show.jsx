import axios from 'axios';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import Button from '../../Components/Form/Button';
import Field from '../../Components/Form/Field';
import TextInput from '../../Components/Form/TextInput';
import AppLayout from '../../Layouts/AppLayout';

// The QR/confirmation code here is a plain text code, not a rendered QR
// barcode image — adding a QR-rendering library was out of Sprint 3's
// approved scope (only @stripe/stripe-js was approved as a new
// dependency). The buyer shows this code to the seller in person; the
// seller types it in below.
export default function Show({ transferId }) {
    const { t } = useTranslation('transfers');
    const [transfer, setTransfer] = useState(null);
    const [loadError, setLoadError] = useState(null);
    const [qrToken, setQrToken] = useState(null);
    const [qrTokenInput, setQrTokenInput] = useState('');
    const [busy, setBusy] = useState(null);
    const [error, setError] = useState(null);

    function load() {
        axios
            .get(`/transfers/${transferId}`)
            .then((response) => setTransfer(response.data.data))
            .catch((err) => setLoadError(err.response?.data?.message ?? t('errors.load_error')));
    }

    useEffect(load, [transferId]);

    function revealCode() {
        setBusy('reveal');
        axios
            .get(`/transfers/${transferId}/qr-token`)
            .then((response) => setQrToken(response.data.data.qr_token))
            .catch((err) => setError(err.response?.data?.message ?? t('errors.generic')))
            .finally(() => setBusy(null));
    }

    function confirm() {
        if (!navigator.geolocation) {
            setError(t('errors.geolocation_unsupported'));
            return;
        }

        setBusy('confirm');
        setError(null);

        navigator.geolocation.getCurrentPosition(
            async (position) => {
                const body = {
                    latitude: position.coords.latitude,
                    longitude: position.coords.longitude,
                };

                const endpoint =
                    transfer.role === 'seller'
                        ? `/transfers/${transferId}/confirm-as-seller`
                        : `/transfers/${transferId}/confirm-as-buyer`;

                try {
                    await axios.post(endpoint, transfer.role === 'seller' ? { ...body, qr_token: qrTokenInput } : body);
                    load();
                } catch (err) {
                    setError(err.response?.data?.message ?? t('errors.generic'));
                } finally {
                    setBusy(null);
                }
            },
            () => {
                setError(t('errors.geolocation_denied'));
                setBusy(null);
            },
        );
    }

    if (loadError) {
        return (
            <AppLayout title={t('title')}>
                <div className="rounded-lg border border-red-800 bg-red-950 p-3 text-sm text-red-300">{loadError}</div>
            </AppLayout>
        );
    }

    if (!transfer) {
        return (
            <AppLayout title={t('title')}>
                <p className="text-slate-400">{t('loading')}</p>
            </AppLayout>
        );
    }

    const myConfirmation = transfer.role === 'seller' ? transfer.seller_confirmed : transfer.buyer_confirmed;
    const canConfirm = transfer.status === 'issued' && !myConfirmation;

    return (
        <AppLayout title={t('title')}>
            <h1 className="text-2xl font-bold">{t('title')}</h1>
            <p className="mt-2 text-slate-400">{t(`role.${transfer.role}`)}</p>

            <dl className="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div>
                    <dt className="text-sm text-slate-400">{t('fields.seller_confirmed')}</dt>
                    <dd className="text-lg font-semibold">{transfer.seller_confirmed ? t('fields.yes') : t('fields.no')}</dd>
                </div>
                <div>
                    <dt className="text-sm text-slate-400">{t('fields.buyer_confirmed')}</dt>
                    <dd className="text-lg font-semibold">{transfer.buyer_confirmed ? t('fields.yes') : t('fields.no')}</dd>
                </div>
                <div>
                    <dt className="text-sm text-slate-400">{t('fields.expires_at')}</dt>
                    <dd className="text-lg font-semibold">{new Date(transfer.expires_at).toLocaleString()}</dd>
                </div>
                <div>
                    <dt className="text-sm text-slate-400">{t('title')}</dt>
                    <dd className="text-lg font-semibold">{t(`status.${transfer.status}`)}</dd>
                </div>
            </dl>

            {error && (
                <div className="mt-4 rounded-lg border border-red-800 bg-red-950 p-3 text-sm text-red-300">{error}</div>
            )}

            {transfer.role === 'buyer' && transfer.status === 'issued' && (
                <div className="mt-8 max-w-sm">
                    {qrToken ? (
                        <p className="rounded-lg border border-slate-700 bg-slate-900 p-3 font-mono text-lg">{qrToken}</p>
                    ) : (
                        <Button type="button" onClick={revealCode} disabled={busy === 'reveal'}>
                            {t('actions.reveal_code')}
                        </Button>
                    )}
                </div>
            )}

            {canConfirm && (
                <div className="mt-8 max-w-sm">
                    {transfer.role === 'seller' && (
                        <Field id="qr_token" label={t('fields.qr_token')}>
                            <TextInput
                                id="qr_token"
                                type="text"
                                value={qrTokenInput}
                                onChange={(event) => setQrTokenInput(event.target.value)}
                                required
                            />
                        </Field>
                    )}

                    <Button type="button" onClick={confirm} disabled={busy === 'confirm'} className="mt-4">
                        {busy === 'confirm' ? t('actions.confirming') : t('actions.confirm')}
                    </Button>
                </div>
            )}
        </AppLayout>
    );
}

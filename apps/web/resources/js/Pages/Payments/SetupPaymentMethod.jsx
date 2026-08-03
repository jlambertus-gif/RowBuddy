import { loadStripe } from '@stripe/stripe-js';
import axios from 'axios';
import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import Button from '../../Components/Form/Button';
import AppLayout from '../../Layouts/AppLayout';

// Raw card data never reaches Laravel: Stripe Elements mounts directly
// into cardElementRef's DOM node and confirms the SetupIntent entirely
// client-side (ADR-027 Architecture Refinements §4). Only the resulting
// setup_intent id — never card details — is ever posted to the backend.
export default function SetupPaymentMethod() {
    const { t } = useTranslation('payments');
    const cardElementRef = useRef(null);
    const stripeRef = useRef(null);
    const elementsRef = useRef(null);
    const [loadError, setLoadError] = useState(null);
    const [saving, setSaving] = useState(false);
    const [saved, setSaved] = useState(false);
    const [error, setError] = useState(null);
    const [clientSecret, setClientSecret] = useState(null);

    useEffect(() => {
        let active = true;

        axios
            .post('/buyer-payment-methods/setup-intent')
            .then(async (response) => {
                if (!active) {
                    return;
                }

                const { client_secret: secret, publishable_key: publishableKey } = response.data.data;
                setClientSecret(secret);

                const stripe = await loadStripe(publishableKey);
                if (!active || !stripe) {
                    return;
                }

                stripeRef.current = stripe;
                const elements = stripe.elements();
                elementsRef.current = elements;
                const card = elements.create('card');
                card.mount(cardElementRef.current);
            })
            .catch((err) => {
                if (active) {
                    setLoadError(err.response?.data?.message ?? t('errors.load_error'));
                }
            });

        return () => {
            active = false;
        };
    }, []);

    async function submitCard(event) {
        event.preventDefault();
        if (!stripeRef.current || !elementsRef.current || !clientSecret) {
            return;
        }

        setSaving(true);
        setError(null);

        const { error: stripeError, setupIntent } = await stripeRef.current.confirmCardSetup(clientSecret, {
            payment_method: { card: elementsRef.current.getElement('card') },
        });

        if (stripeError) {
            setError(stripeError.message ?? t('errors.generic'));
            setSaving(false);
            return;
        }

        try {
            await axios.post('/buyer-payment-methods', { setup_intent_id: setupIntent.id });
            setSaved(true);
        } catch (err) {
            setError(err.response?.data?.message ?? t('errors.generic'));
        } finally {
            setSaving(false);
        }
    }

    if (loadError) {
        return (
            <AppLayout title={t('title')}>
                <div className="rounded-lg border border-red-800 bg-red-950 p-3 text-sm text-red-300">{loadError}</div>
            </AppLayout>
        );
    }

    return (
        <AppLayout title={t('title')}>
            <h1 className="text-2xl font-bold">{t('title')}</h1>

            {saved ? (
                <p className="mt-6 text-slate-300">{t('status.saved')}</p>
            ) : (
                <form onSubmit={submitCard} className="mt-8 max-w-sm">
                    <label className="mb-2 block text-sm text-slate-400" htmlFor="card-element">
                        {t('fields.card')}
                    </label>
                    <div id="card-element" ref={cardElementRef} className="rounded-lg border border-slate-700 bg-slate-900 p-3" />

                    {error && (
                        <div className="mt-2 rounded-lg border border-red-800 bg-red-950 p-3 text-sm text-red-300">{error}</div>
                    )}

                    <Button type="submit" disabled={saving || !clientSecret} className="mt-4">
                        {saving ? t('actions.saving') : t('actions.save')}
                    </Button>
                </form>
            )}
        </AppLayout>
    );
}

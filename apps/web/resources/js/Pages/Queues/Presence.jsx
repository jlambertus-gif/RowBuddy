import axios from 'axios';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import Button from '../../Components/Form/Button';
import ConfidenceBadge from '../../Components/Presence/ConfidenceBadge';
import AppLayout from '../../Layouts/AppLayout';

export default function Presence({ queueId }) {
    const { t } = useTranslation('presence');
    const [session, setSession] = useState(null);
    const [busy, setBusy] = useState(null);
    const [error, setError] = useState(null);
    const [photo, setPhoto] = useState(null);

    function applyResponse(response) {
        setSession(response.data.data);
        setError(null);
    }

    function applyError(requestError) {
        setError(requestError.response?.data?.message ?? t('errors.generic'));
    }

    async function startSession() {
        setBusy('start');

        try {
            applyResponse(
                await axios.post('/presence-sessions', { queue_id: queueId }),
            );
        } catch (requestError) {
            applyError(requestError);
        } finally {
            setBusy(null);
        }
    }

    function recordGpsPing() {
        if (!navigator.geolocation) {
            setError(t('errors.geolocation_unsupported'));
            return;
        }

        setBusy('gps');

        navigator.geolocation.getCurrentPosition(
            async (position) => {
                try {
                    applyResponse(
                        await axios.post(
                            `/presence-sessions/${session.id}/gps-pings`,
                            {
                                latitude: position.coords.latitude,
                                longitude: position.coords.longitude,
                                accuracy_meters: position.coords.accuracy,
                            },
                        ),
                    );
                } catch (requestError) {
                    applyError(requestError);
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

    async function uploadEvidencePhoto(event) {
        event.preventDefault();

        if (!photo) {
            return;
        }

        setBusy('photo');

        const formData = new FormData();
        formData.append('photo', photo);

        try {
            applyResponse(
                await axios.post(
                    `/presence-sessions/${session.id}/evidence-photos`,
                    formData,
                    { headers: { 'Content-Type': 'multipart/form-data' } },
                ),
            );
            setPhoto(null);
        } catch (requestError) {
            applyError(requestError);
        } finally {
            setBusy(null);
        }
    }

    async function endSession() {
        setBusy('end');

        try {
            applyResponse(
                await axios.post(`/presence-sessions/${session.id}/end`),
            );
        } catch (requestError) {
            applyError(requestError);
        } finally {
            setBusy(null);
        }
    }

    const isActive = session?.status === 'active';

    return (
        <AppLayout title={t('title')}>
            <div className="mx-auto max-w-xl">
                <h1 className="text-2xl font-bold">{t('title')}</h1>
                <p className="mt-2 text-slate-400">{t('subtitle')}</p>

                {error && (
                    <div className="mt-6 rounded-lg border border-red-800 bg-red-950 p-3 text-sm text-red-300">
                        {error}
                    </div>
                )}

                {!session && (
                    <Button
                        type="button"
                        disabled={busy === 'start'}
                        onClick={startSession}
                        className="mt-6 w-full"
                    >
                        {busy === 'start' ? t('actions.starting') : t('actions.start')}
                    </Button>
                )}

                {session && (
                    <div className="mt-6 space-y-6">
                        <div className="flex items-center justify-between rounded-2xl border border-slate-800 bg-slate-900 p-6">
                            <div>
                                <p className="text-sm text-slate-400">
                                    {t('session_status')}
                                </p>
                                <p className="text-lg font-semibold">
                                    {t(`status.${session.status}`)}
                                </p>
                            </div>

                            <ConfidenceBadge
                                tier={session.confidence.tier}
                                points={session.confidence.points}
                            />
                        </div>

                        {isActive && (
                            <>
                                <Button
                                    type="button"
                                    disabled={busy !== null}
                                    onClick={recordGpsPing}
                                    className="w-full"
                                >
                                    {busy === 'gps'
                                        ? t('actions.recording_gps')
                                        : t('actions.record_gps')}
                                </Button>

                                <form
                                    onSubmit={uploadEvidencePhoto}
                                    className="space-y-3 rounded-2xl border border-slate-800 bg-slate-900 p-6"
                                >
                                    <label
                                        htmlFor="photo"
                                        className="block text-sm font-medium"
                                    >
                                        {t('fields.photo')}
                                    </label>

                                    <input
                                        id="photo"
                                        type="file"
                                        accept="image/jpeg"
                                        capture="environment"
                                        onChange={(event) =>
                                            setPhoto(event.target.files?.[0] ?? null)
                                        }
                                        className="block w-full text-sm text-slate-300"
                                    />

                                    <Button
                                        type="submit"
                                        disabled={busy !== null || !photo}
                                        className="w-full"
                                    >
                                        {busy === 'photo'
                                            ? t('actions.uploading_photo')
                                            : t('actions.upload_photo')}
                                    </Button>
                                </form>

                                <Button
                                    type="button"
                                    disabled={busy !== null}
                                    onClick={endSession}
                                    className="w-full bg-red-700 hover:bg-red-600"
                                >
                                    {busy === 'end' ? t('actions.ending') : t('actions.end')}
                                </Button>
                            </>
                        )}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}

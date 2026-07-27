import { Link, usePage } from '@inertiajs/react';
import axios from 'axios';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import Button from '../../Components/Form/Button';
import Field from '../../Components/Form/Field';
import TextInput from '../../Components/Form/TextInput';
import Pagination from '../../Components/Pagination';
import QueueCard from '../../Components/Queues/QueueCard';
import AppLayout from '../../Layouts/AppLayout';

const PER_PAGE = 20;

export default function Discover() {
    const { t } = useTranslation('queues');
    const { auth } = usePage().props;
    const [coords, setCoords] = useState({ latitude: '', longitude: '' });
    const [result, setResult] = useState(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);

    async function search(page) {
        setLoading(true);
        setError(null);

        try {
            const response = await axios.get('/queues/discover', {
                params: {
                    latitude: coords.latitude,
                    longitude: coords.longitude,
                    page,
                    per_page: PER_PAGE,
                },
            });

            setResult(response.data);
        } catch (requestError) {
            setResult(null);
            setError(
                requestError.response?.data?.message ?? t('discover.load_error'),
            );
        } finally {
            setLoading(false);
        }
    }

    function submit(event) {
        event.preventDefault();
        search(1);
    }

    return (
        <AppLayout title={t('discover.title')}>
            <h1 className="text-2xl font-bold">{t('discover.title')}</h1>
            <p className="mt-2 text-slate-400">{t('discover.subtitle')}</p>

            <form
                onSubmit={submit}
                className="mt-6 grid max-w-xl grid-cols-2 gap-4"
            >
                <Field id="latitude" label={t('fields.latitude')}>
                    <TextInput
                        id="latitude"
                        type="number"
                        step="any"
                        value={coords.latitude}
                        onChange={(event) =>
                            setCoords((current) => ({
                                ...current,
                                latitude: event.target.value,
                            }))
                        }
                        required
                    />
                </Field>

                <Field id="longitude" label={t('fields.longitude')}>
                    <TextInput
                        id="longitude"
                        type="number"
                        step="any"
                        value={coords.longitude}
                        onChange={(event) =>
                            setCoords((current) => ({
                                ...current,
                                longitude: event.target.value,
                            }))
                        }
                        required
                    />
                </Field>

                <Button type="submit" disabled={loading} className="col-span-2">
                    {loading ? t('discover.searching') : t('discover.search_button')}
                </Button>
            </form>

            {error && (
                <div className="mt-6 rounded-lg border border-red-800 bg-red-950 p-3 text-sm text-red-300">
                    {error}
                </div>
            )}

            {result && (
                <div className="mt-8">
                    {result.data.length === 0 ? (
                        <p className="text-slate-400">{t('discover.empty')}</p>
                    ) : (
                        <>
                            <div className="grid gap-4 sm:grid-cols-2">
                                {result.data.map((queue) => (
                                    <QueueCard
                                        key={queue.id}
                                        queue={queue}
                                        actions={
                                            auth?.user && (
                                                <Link
                                                    href={`/queues/${queue.id}/presence`}
                                                    className="inline-block rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-sky-500"
                                                >
                                                    {t('discover.claim_presence')}
                                                </Link>
                                            )
                                        }
                                    />
                                ))}
                            </div>

                            <Pagination
                                page={result.meta.page}
                                perPage={result.meta.per_page}
                                total={result.meta.total}
                                hasMore={result.meta.has_more}
                                onPageChange={(page) => search(page)}
                            />
                        </>
                    )}
                </div>
            )}
        </AppLayout>
    );
}

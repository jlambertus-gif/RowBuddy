import { useForm, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import Button from '../../Components/Form/Button';
import Field from '../../Components/Form/Field';
import TextInput from '../../Components/Form/TextInput';
import AppLayout from '../../Layouts/AppLayout';

export default function Submit() {
    const { t } = useTranslation('queues');
    const { flash } = usePage().props;

    const { data, setData, post, processing, errors } = useForm({
        category: '',
        jurisdiction_country: '',
        latitude: '',
        longitude: '',
        radius_meters: '',
    });

    function submit(event) {
        event.preventDefault();
        post('/queues');
    }

    return (
        <AppLayout title={t('submit.title')}>
            <div className="mx-auto max-w-xl">
                <h1 className="text-2xl font-bold">{t('submit.title')}</h1>
                <p className="mt-2 text-slate-400">{t('submit.subtitle')}</p>

                {flash?.status && (
                    <div className="mt-6 rounded-lg border border-emerald-800 bg-emerald-950 p-3 text-sm text-emerald-300">
                        {flash.status}
                    </div>
                )}

                <form onSubmit={submit} className="mt-6 space-y-5">
                    <Field
                        id="category"
                        label={t('fields.category')}
                        error={errors.category}
                    >
                        <TextInput
                            id="category"
                            type="text"
                            value={data.category}
                            onChange={(event) =>
                                setData('category', event.target.value)
                            }
                            required
                        />
                    </Field>

                    <Field
                        id="jurisdiction_country"
                        label={t('fields.jurisdiction_country')}
                        error={errors.jurisdiction_country}
                    >
                        <TextInput
                            id="jurisdiction_country"
                            type="text"
                            maxLength={2}
                            value={data.jurisdiction_country}
                            onChange={(event) =>
                                setData(
                                    'jurisdiction_country',
                                    event.target.value.toUpperCase(),
                                )
                            }
                            className="uppercase"
                            required
                        />
                    </Field>

                    <div className="grid grid-cols-2 gap-4">
                        <Field
                            id="latitude"
                            label={t('fields.latitude')}
                            error={errors.latitude}
                        >
                            <TextInput
                                id="latitude"
                                type="number"
                                step="any"
                                value={data.latitude}
                                onChange={(event) =>
                                    setData('latitude', event.target.value)
                                }
                                required
                            />
                        </Field>

                        <Field
                            id="longitude"
                            label={t('fields.longitude')}
                            error={errors.longitude}
                        >
                            <TextInput
                                id="longitude"
                                type="number"
                                step="any"
                                value={data.longitude}
                                onChange={(event) =>
                                    setData('longitude', event.target.value)
                                }
                                required
                            />
                        </Field>
                    </div>

                    <Field
                        id="radius_meters"
                        label={t('fields.radius_meters')}
                        error={errors.radius_meters}
                    >
                        <TextInput
                            id="radius_meters"
                            type="number"
                            step="any"
                            min="1"
                            value={data.radius_meters}
                            onChange={(event) =>
                                setData('radius_meters', event.target.value)
                            }
                            required
                        />
                    </Field>

                    <Button type="submit" disabled={processing} className="w-full">
                        {processing
                            ? t('submit.submitting')
                            : t('submit.submit_button')}
                    </Button>
                </form>
            </div>
        </AppLayout>
    );
}

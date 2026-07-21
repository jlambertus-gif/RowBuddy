import { useTranslation } from 'react-i18next';

export default function Welcome() {
    const { t } = useTranslation('common');

    return (
        <div className="flex min-h-screen items-center justify-center bg-neutral-50 dark:bg-neutral-900">
            <div className="text-center">
                <h1 className="text-3xl font-semibold text-neutral-900 dark:text-neutral-100">
                    {t('welcome_heading')}
                </h1>
                <p className="mt-2 text-neutral-600 dark:text-neutral-400">
                    {t('welcome_subheading')}
                </p>
            </div>
        </div>
    );
}

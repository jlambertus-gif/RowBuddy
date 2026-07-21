<?php

declare(strict_types=1);

/**
 * ADR-002 (docs/decisions/002-multilingual-from-start.md) requires English
 * and Spanish translations for every key, with English as fallback. This
 * test fails CI the moment the two locales drift apart, on both the
 * backend (Laravel lang/) and frontend (i18next resources/js/lang/) sides,
 * instead of relying on code review to catch it.
 */
function flattenTranslationKeys(array $translations, string $prefix = ''): array
{
    $keys = [];

    foreach ($translations as $key => $value) {
        $fullKey = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

        if (is_array($value)) {
            $keys = [...$keys, ...flattenTranslationKeys($value, $fullKey)];
        } else {
            $keys[] = $fullKey;
        }
    }

    sort($keys);

    return $keys;
}

it('has matching backend translation keys for every namespace in en and es', function () {
    $basePath = dirname(__DIR__, 2);
    $enPath = $basePath.'/lang/en';
    $esPath = $basePath.'/lang/es';

    $enFiles = glob($enPath.'/*.php') ?: [];

    expect($enFiles)->not->toBeEmpty('Expected at least one backend translation namespace under lang/en.');

    foreach ($enFiles as $enFile) {
        $namespace = basename($enFile, '.php');
        $esFile = $esPath.DIRECTORY_SEPARATOR.$namespace.'.php';

        expect(is_file($esFile))->toBeTrue("Missing lang/es/{$namespace}.php for backend namespace [{$namespace}].");

        $enKeys = flattenTranslationKeys(require $enFile);
        $esKeys = flattenTranslationKeys(require $esFile);

        expect($esKeys)->toBe($enKeys, "Translation key mismatch between lang/en/{$namespace}.php and lang/es/{$namespace}.php.");
    }
});

it('has matching frontend translation keys for every namespace in en and es', function () {
    $basePath = dirname(__DIR__, 2);
    $enPath = $basePath.'/resources/js/lang/en';
    $esPath = $basePath.'/resources/js/lang/es';

    $enFiles = glob($enPath.'/*.json') ?: [];

    expect($enFiles)->not->toBeEmpty('Expected at least one frontend translation namespace under resources/js/lang/en.');

    foreach ($enFiles as $enFile) {
        $namespace = basename($enFile, '.json');
        $esFile = $esPath.DIRECTORY_SEPARATOR.$namespace.'.json';

        expect(is_file($esFile))->toBeTrue("Missing resources/js/lang/es/{$namespace}.json for frontend namespace [{$namespace}].");

        $enKeys = flattenTranslationKeys(json_decode(file_get_contents($enFile), true));
        $esKeys = flattenTranslationKeys(json_decode(file_get_contents($esFile), true));

        expect($esKeys)->toBe($enKeys, "Translation key mismatch between resources/js/lang/en/{$namespace}.json and resources/js/lang/es/{$namespace}.json.");
    }
});

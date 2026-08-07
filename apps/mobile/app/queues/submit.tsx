import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Pressable, StyleSheet, Text, TextInput, View } from 'react-native';

import { ApiError } from '@/api/client';
import { useSubmitQueue } from '@/features/queues/hooks/useSubmitQueue';
import { getCurrentCoordinates } from '@/lib/location';

/**
 * Mobile Sprint 5 (ADR-028 §3). Mirrors web's Queues/Submit.jsx fields
 * exactly (category, jurisdiction_country, latitude, longitude,
 * radius_meters); unlike web, which requires manual lat/lng entry, this
 * screen offers a "use my current location" convenience button — GPS is
 * requested contextually here (only on this button press), not at
 * screen mount, matching the same permission-request posture already
 * established for discovery/transfer confirmation.
 */
export default function SubmitQueue() {
  const { t } = useTranslation('queues');
  const submitQueue = useSubmitQueue();

  const [category, setCategory] = useState('');
  const [jurisdictionCountry, setJurisdictionCountry] = useState('');
  const [latitude, setLatitude] = useState('');
  const [longitude, setLongitude] = useState('');
  const [radiusMeters, setRadiusMeters] = useState('');
  const [locating, setLocating] = useState(false);
  const [locationError, setLocationError] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  async function handleUseCurrentLocation() {
    setLocationError(null);
    setLocating(true);

    try {
      const coordinates = await getCurrentCoordinates();
      setLatitude(String(coordinates.latitude));
      setLongitude(String(coordinates.longitude));
    } catch {
      setLocationError(t('submit.location_error'));
    } finally {
      setLocating(false);
    }
  }

  function handleSubmit() {
    setError(null);

    submitQueue.mutate(
      {
        category,
        jurisdiction_country: jurisdictionCountry.toUpperCase(),
        latitude: parseFloat(latitude),
        longitude: parseFloat(longitude),
        radius_meters: parseFloat(radiusMeters),
      },
      {
        onError: (submitError) => {
          setError(
            submitError instanceof ApiError ? submitError.message : t('submit.generic_error'),
          );
        },
      },
    );
  }

  const canSubmit =
    category.trim().length > 0 &&
    jurisdictionCountry.trim().length === 2 &&
    latitude.trim().length > 0 &&
    longitude.trim().length > 0 &&
    radiusMeters.trim().length > 0;

  if (submitQueue.isSuccess) {
    return (
      <View style={styles.centered}>
        <Text style={styles.success}>{t('submit.success')}</Text>
      </View>
    );
  }

  return (
    <View style={styles.container}>
      <Text style={styles.title}>{t('submit.title')}</Text>
      <Text style={styles.subtitle}>{t('submit.subtitle')}</Text>

      <Text style={styles.label}>{t('fields.category')}</Text>
      <TextInput
        style={styles.input}
        value={category}
        onChangeText={setCategory}
        testID="queue-category-input"
      />

      <Text style={styles.label}>{t('fields.jurisdiction_country')}</Text>
      <TextInput
        style={styles.input}
        value={jurisdictionCountry}
        onChangeText={(value) => setJurisdictionCountry(value.toUpperCase())}
        autoCapitalize="characters"
        maxLength={2}
        testID="queue-country-input"
      />

      <Pressable
        style={styles.locationButton}
        onPress={handleUseCurrentLocation}
        disabled={locating}
        testID="use-current-location-button"
      >
        <Text style={styles.locationButtonText}>
          {locating ? t('submit.locating') : t('submit.use_current_location_button')}
        </Text>
      </Pressable>
      {locationError && <Text style={styles.error}>{locationError}</Text>}

      <Text style={styles.label}>{t('fields.latitude')}</Text>
      <TextInput
        style={styles.input}
        value={latitude}
        onChangeText={setLatitude}
        keyboardType="numbers-and-punctuation"
        testID="queue-latitude-input"
      />

      <Text style={styles.label}>{t('fields.longitude')}</Text>
      <TextInput
        style={styles.input}
        value={longitude}
        onChangeText={setLongitude}
        keyboardType="numbers-and-punctuation"
        testID="queue-longitude-input"
      />

      <Text style={styles.label}>{t('fields.radius_meters')}</Text>
      <TextInput
        style={styles.input}
        value={radiusMeters}
        onChangeText={setRadiusMeters}
        keyboardType="numeric"
        testID="queue-radius-input"
      />

      {error && <Text style={styles.error}>{error}</Text>}

      <Pressable
        style={styles.button}
        onPress={handleSubmit}
        disabled={!canSubmit || submitQueue.isPending}
        testID="submit-queue-button"
      >
        <Text style={styles.buttonText}>
          {submitQueue.isPending ? t('submit.submitting') : t('submit.submit_button')}
        </Text>
      </Pressable>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#fff',
    padding: 24,
    gap: 4,
  },
  centered: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#fff',
    gap: 8,
    padding: 24,
  },
  title: {
    fontSize: 22,
    fontWeight: '600',
  },
  subtitle: {
    fontSize: 14,
    color: '#666',
    marginBottom: 12,
  },
  label: {
    fontSize: 13,
    fontWeight: '500',
    color: '#666',
    marginTop: 12,
  },
  input: {
    borderWidth: 1,
    borderColor: '#ccc',
    borderRadius: 8,
    paddingHorizontal: 12,
    paddingVertical: 10,
    marginTop: 4,
  },
  locationButton: {
    marginTop: 12,
    alignItems: 'center',
    paddingVertical: 10,
    borderWidth: 1,
    borderColor: '#2563eb',
    borderRadius: 8,
  },
  locationButtonText: {
    color: '#2563eb',
    fontWeight: '600',
  },
  error: {
    color: '#dc2626',
    fontSize: 13,
    marginTop: 8,
  },
  success: {
    color: '#16a34a',
    fontSize: 16,
    fontWeight: '600',
    textAlign: 'center',
  },
  button: {
    backgroundColor: '#2563eb',
    borderRadius: 8,
    paddingVertical: 14,
    alignItems: 'center',
    marginTop: 20,
  },
  buttonText: {
    color: '#fff',
    fontWeight: '600',
  },
});

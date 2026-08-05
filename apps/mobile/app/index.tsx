import { useTranslation } from 'react-i18next';
import { StyleSheet, Text, View } from 'react-native';

/**
 * Sprint 0 placeholder route only — proves Expo Router, i18n, and the
 * TanStack Query provider are wired up together. Not a real product
 * screen: splash/auth/home are all reserved (see src/features/*) for
 * later sprints per ADR-028.
 */
export default function SprintZeroPlaceholder() {
  const { t } = useTranslation();

  return (
    <View style={styles.container}>
      <Text style={styles.title}>{t('app_name')}</Text>
      <Text style={styles.subtitle}>{t('sprint0_placeholder')}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#fff',
    gap: 8,
  },
  title: {
    fontSize: 24,
    fontWeight: '600',
  },
  subtitle: {
    fontSize: 14,
    color: '#666',
  },
});

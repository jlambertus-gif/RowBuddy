# Localization

## Initial locales

- en
- es

## Rules

- English is the fallback locale.
- User-facing text must use translation keys.
- Language, country, currency, and timezone are independent settings.
- User-generated content stores its original locale.
- Dates, times, numbers, and currencies use locale-aware formatting.
- New features must include both English and Spanish translation keys.
- Translation-key parity between `en` and `es` must be enforced by an
  automated test, both backend (Laravel `lang/`) and frontend (i18next
  resource bundles).

## Layers

- **Backend**: Laravel `lang/en` and `lang/es`, namespaced per module.
- **Frontend**: i18next with matching `en`/`es` resource bundles under
  `resources/js/lang/`.

## Suggested namespaces (shared by backend and frontend)

- common
- auth
- queues
- auctions
- bids
- payments
- transfers
- disputes
- notifications
- admin

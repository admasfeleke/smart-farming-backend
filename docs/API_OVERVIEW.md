# API Overview

The Smart Farming Ethiopia backend exposes a farmer-focused REST API under `/api/v1`.

The API is designed for the Flutter mobile app and supports offline-first field workflows. Laravel remains the system of record, while the mobile app caches local data and syncs when connectivity is restored.

## Authentication

The API uses Laravel Sanctum token authentication.

Typical flow:

```text
POST /api/v1/auth/login
  -> validates phone/password
  -> confirms farmer role for mobile access
  -> returns access token, refresh token, and farmer profile context
```

Authenticated requests must include:

```http
Authorization: Bearer <access-token>
Accept: application/json
```

The mobile app also sends:

```http
Accept-Language: <language-code>
X-App-Language: <language-code>
```

Supported language codes:

- `am`
- `om`
- `ti`
- `en`

## API Areas

Main endpoint groups:

- authentication
- farms
- plots
- plantings
- disease reports
- disease report media
- disease prevention
- soil health
- weather data
- yield prediction
- alerts
- sync/health checks

## Data Ownership

Farmer API responses must be scoped to the authenticated farmer.

Examples:

- a farmer can list only their own farms
- a farmer can access only plots under their farms
- a farmer can create plantings only under their plots
- a farmer can view only their own disease reports
- a farmer can view only their own soil records
- a farmer can view only their own alerts

This rule must be enforced on the backend, not only in the Flutter app.

## Disease Report Flow

```text
Farmer selects crop and field context
Farmer captures leaf image
Flutter uploads image and context
Laravel stores report and image evidence
Laravel optionally calls inference service
Laravel assigns report to regional reviewer
Supporter/expert reviews evidence
Farmer receives updated status and guidance after sync
```

The API must preserve:

- original image evidence
- selected crop
- farm/plot/planting context
- offline provisional result
- server inference result
- expert/supporter final decision

## Media Access

Disease and soil evidence images should not be treated as public anonymous files.

Recommended access rules:

- farmer can access only their own media
- supporter/expert can access media only for assigned or authorized reports
- admin access is role-scoped
- media routes should use authenticated authorization or short-lived signed URLs

## Error Format

Production API errors should be farmer-safe and client-readable.

Avoid exposing:

- stack traces
- SQL details
- internal file paths
- raw exception class names in farmer-facing messages

Recommended response style:

```json
{
  "message": "Unable to complete the request.",
  "code": "request_failed"
}
```

## Localization

The Flutter app sends the selected language using headers. The backend localizes major farmer-facing API messages and recommendation summaries where practical.

Database-authored treatment registry content should use translated fields or a translation table for full production localization.


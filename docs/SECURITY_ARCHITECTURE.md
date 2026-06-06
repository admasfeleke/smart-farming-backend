# Security Architecture

This document explains the backend security design for professional review.

## Trust Boundaries

```text
Farmer phone
  -> untrusted network
  -> Laravel API
  -> MySQL/storage
  -> optional inference service
```

The mobile app is not trusted to enforce ownership or role rules. All sensitive checks must happen in Laravel.

## Authentication

The API uses Laravel Sanctum bearer tokens.

Authentication responsibilities:

- validate farmer credentials
- issue short-lived access token
- issue refresh token where supported
- reject inactive users
- restrict mobile login to farmer role
- revoke tokens on logout
- throttle login attempts

Offline login is a mobile continuity feature only. It does not grant new server access while offline.

## Authorization

Authorization is enforced by:

- route middleware
- model ownership checks
- role checks
- region/assignment scoping for back-office users

Farmer mobile authorization rule:

```text
authenticated user id must match the farmer_id or owner relation of the requested resource
```

Back-office authorization rule:

```text
supporter/expert/admin access depends on assigned role, region, and report responsibility
```

## Role Separation

The Flutter app is designed for farmers.

Back-office users use Laravel/Filament:

- supporter
- expert
- regional administrator
- super administrator

This separation reduces mobile API exposure and keeps sensitive review actions in the controlled administrative interface.

## Request Validation

Create/update endpoints should validate:

- required fields
- numeric IDs
- date format
- enum values
- file type
- image size
- farmer ownership of related IDs
- crop/plot/planting consistency

Validation must happen before database writes.

## Rate Limiting

Recommended API limit groups:

- auth/login and register: strict
- write operations: moderate
- read operations: normal
- media endpoints: protected from abuse
- inference-triggering endpoints: strict timeout and rate protection

## Evidence Storage

Farmer images are sensitive field evidence.

Security requirements:

- do not commit uploaded evidence to GitHub
- store evidence paths in database
- authorize access before serving media
- avoid public predictable access when possible
- use signed URLs only when expiration and signature checks are reliable

## Inference Service Safety

The Python inference service is optional support infrastructure.

Security requirements:

- bind locally or behind a private network
- use token authentication if exposed beyond localhost
- enforce timeout in Laravel
- never block report storage if inference fails
- return manual-review fallback when model is unavailable

AI output is not the final authority.

## Treatment Guidance Safety

Treatment guidance should be exposed only when backend rules allow it.

Actionable treatment requires:

- confirmed or verified report
- crop family match
- non-uncertain inference/review state
- approved guidance for the crop/disease pair

If those conditions are not met, the API should return monitoring or pending-review guidance instead of pesticide instructions.

## Production Hardening Checklist

- `APP_DEBUG=false`
- HTTPS enabled
- secure `APP_KEY`
- production database credentials stored outside Git
- CORS restricted
- logs protected
- storage permissions checked
- public storage reviewed
- file upload limits enforced
- queue workers supervised if used
- backup and restore tested
- error reporting configured
- health checks monitored

## GitHub Safety Checklist

Before pushing:

```powershell
git status --short
```

Confirm these are not staged:

- `.env`
- `vendor/`
- `node_modules/`
- `storage/logs/`
- uploaded evidence files
- database dumps
- local test credentials


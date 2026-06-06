# Security Policy

## Scope

This security policy applies to the Smart Farming Ethiopia Laravel backend.

Related components:

- Flutter farmer mobile app
- Python inference service
- MySQL database
- Filament back-office

## Security Model

The backend is the system of record. Farmer mobile clients must authenticate through the API and can only access farmer-owned data. Administrative, supporter, and expert workflows are handled through the Laravel/Filament back-office and are not exposed as general farmer mobile actions.

Core controls:

- Laravel Sanctum token authentication
- role-based access control
- farmer-owned data scoping
- request validation
- API rate limiting
- signed or authenticated media access
- safe AI/inference review workflow
- production-safe error handling

## Reporting Security Issues

Report security issues privately to:

- Email: admasu.feleke21@gmail.com
- Phone: 0900824328

Do not publish security vulnerabilities publicly before they are reviewed and fixed.

## Secrets

Never commit:

- `.env`
- database credentials
- app keys
- access tokens
- API tokens
- private uploaded farmer evidence
- production storage files
- database dumps

Use `.env.example` for safe configuration examples only.

## Production Requirements

Before production deployment:

- set `APP_ENV=production`
- set `APP_DEBUG=false`
- use HTTPS
- use strong `APP_KEY`
- restrict CORS to trusted origins
- configure backups
- configure log rotation
- verify storage permissions
- verify rate limits
- verify queue workers if used
- verify inference timeout and failure behavior

## AI and Treatment Safety

AI inference output is supporting evidence only. It must not automatically approve chemical treatment unless the backend review rules permit it.

Treatment guidance should be actionable only when:

- the report is confirmed or verified
- the crop matches the selected crop family
- the result is not uncertain
- treatment guidance is approved for the crop/disease case


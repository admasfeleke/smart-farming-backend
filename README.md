# Smart Farming Ethiopia Backend

Laravel API and Filament back-office for the Smart Farming Ethiopia project.

This backend is the system of record for farmer data, disease reports, expert/supporter review, soil health, weather records, alerts, yield prediction, treatment guidance, and administrative workflows. It works with the Flutter farmer mobile app and the optional Python inference service.

## Project Purpose

Smart Farming Ethiopia was developed as an applied technology-transfer project for career promotion from Assistant Instructor to Instructor.

- Developer: Admasu Feleke Mulatu
- Email: admasu.feleke21@gmail.com
- Phone: 0900824328
- Institution: Dalocha Polytechnic College
- Approval context: Technology Transfer Core, Dalocha Polytechnic College

## Main Capabilities

- Farmer-facing REST API
- Laravel Sanctum authentication
- Farmer-owned farm, plot, planting, soil, disease, weather, and alert data
- Filament back-office for administrators, supporters, and experts
- Disease report triage and review workflow
- Image evidence storage and secure preview
- Treatment guidance rules and pesticide/treatment registry support
- Schema-backed localized treatment registry content
- Disease prevention recommendations
- Soil health recommendation endpoint with optional sensor/IoT metadata
- Weather monitoring and summaries with optional sensor/IoT metadata
- Yield prediction endpoint
- Optional online inference integration
- Operational health checks and release-gate commands

## System Roles

- Farmer: uses the Flutter mobile app.
- Supporter: reviews assigned regional farmer reports and provides field support.
- Expert: validates disease reports and treatment guidance where needed.
- Regional administrator: manages scoped regional operations.
- Super administrator: manages global system configuration.

Administrative access is intentionally separated from the farmer mobile app.

## Architecture

```text
Flutter farmer app
  |
  | REST API / Sanctum
  v
Laravel backend
  |
  |-- MySQL database
  |-- Filament back-office
  |-- Local/public storage for evidence
  |-- Optional Python inference service
```

Disease scan flow:

```text
Farmer captures image
  -> Flutter sends image, selected farm/plot/crop, and field context
  -> Laravel stores disease report and image evidence
  -> Laravel optionally calls inference service
  -> Report is assigned to regional reviewer
  -> Supporter/expert confirms or rejects
  -> Farmer sees synced status and guidance
```

## Requirements

- PHP 8.2+
- Composer
- Node.js and npm
- MySQL
- Laravel 12
- Filament 4

## Setup

```powershell
cd C:\dev\smart-farming
composer install
npm install
copy .env.example .env
php artisan key:generate
php artisan migrate --seed
npm run build
```

After pulling new code, always apply migrations:

```powershell
php artisan migrate
```

Run locally:

```powershell
php artisan serve --host=0.0.0.0 --port=8000
```

For frontend assets during development:

```powershell
npm run dev
```

## Environment Configuration

Do not commit `.env`.

Important settings:

```dotenv
APP_NAME="Smart Farming Ethiopia"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=smart_farming
DB_USERNAME=root
DB_PASSWORD=

INFERENCE_ENABLED=true
INFERENCE_BASE_URL=http://127.0.0.1:9010
INFERENCE_ENDPOINT=/predict
INFERENCE_HEALTH_ENDPOINT=/health
INFERENCE_TIMEOUT_SECONDS=15
INFERENCE_REVIEW_ONLY_MODE=false
```

For mobile testing on a physical phone, the Flutter app must use the computer LAN IP, not `127.0.0.1`.

## API Areas

The API under `/api/v1` includes:

- Authentication and token refresh
- Farms, plots, and plantings
- Disease reports and media evidence
- Disease prevention
- Soil health, including optional sensor context
- Weather records, including optional sensor context
- Yield prediction
- Alerts
- Health and sync support endpoints

## Back-Office

The back-office is powered by Filament.

Typical responsibilities:

- review disease reports
- inspect farmer image evidence
- confirm/reject disease diagnosis
- manage region-scoped work
- review treatment guidance readiness
- monitor system operations

## Sensor-Ready Monitoring API

Soil health and weather records support manual farmer entries today and optional IoT/sensor ingestion when devices are available.

Optional monitoring fields:

- `data_source`
- `sensor_device_id`
- `sensor_reading_id`
- `sensor_payload`
- `field_context`
- `confidence_score` for soil health
- `battery_level` and `signal_quality` for weather sensors

Example soil-health JSON:

```json
{
  "plot_id": 7,
  "ph_level": 6.4,
  "nitrogen": 42,
  "phosphorus": 18,
  "potassium": 220,
  "moisture_level": 34,
  "test_date": "2026-06-08",
  "test_method": "iot_sensor",
  "data_source": "iot",
  "sensor_device_id": "soil-node-001",
  "sensor_reading_id": "reading-20260608-001",
  "sensor_payload": {
    "firmware": "1.0.0",
    "raw_moisture": 684
  },
  "field_context": {
    "irrigated_recently": true,
    "crop_stage": "vegetative"
  },
  "confidence_score": 92
}
```

Example weather JSON:

```json
{
  "farm_id": 5,
  "plot_id": 7,
  "temperature": 27.5,
  "humidity": 72,
  "precipitation": 0,
  "wind_speed": 8,
  "soil_moisture": 31,
  "data_source": "weather_station",
  "sensor_device_id": "weather-node-001",
  "sensor_reading_id": "weather-20260608-001",
  "sensor_payload": {
    "firmware": "1.0.0"
  },
  "battery_level": 86,
  "signal_quality": 78,
  "recorded_at": "2026-06-08T08:30:00Z"
}
```

## Disease Scan Context

Disease scan uploads support a first-class `field_context` JSON column in addition to existing `scan_metadata`. This makes decision support more reliable when one crop exists in multiple farms or plots.

Typical scan context:

```json
{
  "growth_stage": "flowering",
  "symptom_days": 4,
  "recent_rain": true,
  "field_notes": "Symptoms started on lower leaves",
  "crop_name": "Tomato",
  "plot_name": "Plot A"
}
```

The backend still validates that selected `planting_id`, `plot_id`, and `crop_id` are logically aligned before accepting a report.

## Registry Localization

Treatment registry tables support localized content at schema level:

- `pesticide_products.localized_names`
- `pesticide_products.localized_active_ingredients`
- `pesticide_products.localized_label_warnings`
- `treatment_recommendations.localized_content`

Expected language keys:

```json
{
  "am": {
    "title": "Amharic title",
    "summary": "Amharic summary"
  },
  "om": {
    "title": "Afaan Oromo title"
  },
  "ti": {
    "title": "Tigrinya title"
  },
  "en": {
    "title": "English title"
  }
}
```

If localized registry content is missing, the API falls back to the existing English registry fields and then to configured static localization.

## Inference Integration

The backend can call a separate Python service for online inference.

Health:

```text
GET http://127.0.0.1:9010/health
```

Prediction:

```text
POST http://127.0.0.1:9010/predict
```

If the inference service is down, Laravel should keep the uploaded report for review rather than losing farmer evidence.

## Operational Commands

```powershell
php artisan ops:health-check --json
php artisan ops:inference-kpi --json
php artisan ops:release-gate --target=controlled --json
php artisan ops:release-gate --target=autonomous --json
```

These commands support production-readiness checks for inference health, review-only mode, KPI thresholds, and review backlog.

## Testing

```powershell
php artisan test
```

Useful validation before GitHub push:

```powershell
composer validate
php artisan test
npm run build
```

## GitHub Hygiene

Do not commit:

- `.env`
- `vendor/`
- `node_modules/`
- `storage/logs/`
- `storage/framework/cache/`
- uploaded private evidence files
- generated public storage symlinks
- database dumps
- temporary scripts or one-off debug files

Commit:

- source code
- migrations
- seeders/factories needed to reproduce baseline data
- config files without secrets
- `composer.lock`
- `package-lock.json`
- public non-sensitive assets
- documentation

## Security Documentation

Professional reviewers should start with:

- [Security Policy](SECURITY.md)
- [API Overview](docs/API_OVERVIEW.md)
- [Security Architecture](docs/SECURITY_ARCHITECTURE.md)

## Related Components

- Flutter mobile app: `C:\Users\Admas\smart_farm`
- Inference service: `C:\dev\smart-farming-inference`

Recommended GitHub organization:

- `smart-farming-mobile`
- `smart-farming-backend`
- `smart-farming-inference`

Use separate repositories unless you intentionally want a monorepo.

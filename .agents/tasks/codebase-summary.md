# HAVEN Codebase Summary

> Scan date: {{TODAY}}  
> Repository root: `c:\xampp\htdocs\Haven-Ai`  
> Laravel project: `haven/`  
> ML backend: `ml_backend/`

---

## Executive Summary

HAVEN ("Haven AI") is a **capstone research project** (CINS, Group 3) that provides ML-assisted engine diagnostic support for internal combustion engines. It is a **two-service system**:

1. **Laravel 12 web application** (`haven/`) — user-facing frontend and API. Handles authentication, vehicle management, audio/vibration file capture, and orchestrates ML requests.
2. **FastAPI ML backend** (`ml_backend/`) — Python microservice on port 8001 that extracts audio/vibration features and classifies engine condition into five classes.

The project is described internally as a staged build. **Stages 1–6 are implemented**; Stages 7–8 (Learning Modules, future features) are noted as TODO in the UI.

---

## 1. Overall Architecture and Tech Stack

### Laravel Application (`haven/`)

| Layer | Technology |
|---|---|
| Framework | Laravel 12 (PHP 8.2+) |
| Auth scaffolding | Laravel Breeze v2 |
| Database | SQLite (dev), compatible with MySQL/PostgreSQL |
| Frontend build | Vite 7 + Laravel Vite Plugin |
| CSS framework | Tailwind CSS v3 + `@tailwindcss/forms` plugin |
| JS interactivity | Alpine.js v3 |
| HTTP client (server-side) | Laravel `Http` facade (Guzzle) |
| Queue driver | Database (declared in `.env.example`) |
| Mail driver | Log (dev only) |
| Testing | PHPUnit 11 |

**Evidence:** `composer.json`, `package.json`, `vite.config.js`, `tailwind.config.js`, `.env.example`

### ML Backend (`ml_backend/`)

| Layer | Technology |
|---|---|
| API framework | FastAPI 0.115 |
| ASGI server | Uvicorn 0.30 |
| Signal processing | librosa 0.10, soundfile 0.12, scipy 1.13 |
| ML | scikit-learn 1.5 (rule-based fallback built-in; trained `model.pkl` is a drop-in) |
| Numerics | numpy 1.26, pandas 2.2 |

**Evidence:** `ml_backend/requirements.txt`, `ml_backend/main.py`

---

## 2. Database Schema / Migrations

Five migrations exist. All use SQLite in development.

### `users` (0001_01_01_000000)
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| name | string | |
| email | string unique | |
| email_verified_at | timestamp nullable | |
| password | string | bcrypt hashed |
| remember_token | string | |
| timestamps | | |

Companion tables created in same migration: `password_reset_tokens`, `sessions`.

### `vehicles` (2026_10_19_162630)
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| user_id | FK → users | cascadeOnDelete |
| nickname | string nullable | e.g. "My Toyota" |
| make | string | |
| model | string | |
| year | smallint unsigned | |
| plate_number | string nullable | |
| color | string nullable | |
| engine_type | string nullable | e.g. "2NZ-FE 1.3L" |
| transmission | string nullable | Manual/Automatic/CVT/Other |
| mileage_km | int unsigned nullable | |
| notes | text nullable | |
| timestamps | | |

### `diagnostics` (2026_10_19_173559)
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| user_id | FK → users | cascadeOnDelete |
| vehicle_id | FK → vehicles | cascadeOnDelete |
| audio_path | string nullable | stored webm/wav/mp3 path |
| audio_duration_sec | smallint unsigned nullable | |
| audio_original_name | string nullable | |
| vibration_path | string nullable | uploaded CSV/JSON path |
| vibration_sample_count | int unsigned nullable | |
| vibration_peak_g | float nullable | peak acceleration in g |
| vibration_rms_g | float nullable | RMS acceleration in g |
| status | enum | draft / submitted / processing / completed / failed |
| notes | text nullable | |
| ml_condition | string nullable | e.g. normal, knocking, misfiring |
| ml_confidence | float nullable | 0.0 – 1.0 |
| ml_result_raw | json nullable | full FastAPI response payload |
| ml_processed_at | timestamp nullable | |
| timestamps | | |

**Evidence:** `database/migrations/2026_10_19_162630_create_vehicles_table.php`, `database/migrations/2026_10_19_173559_create_diagnostics_table.php`

Standard Laravel framework migrations also present: `cache` table, `jobs` table.

---

## 3. Models and Their Relationships

### `User` (`app/Models/User.php`)
- Extends `Illuminate\Foundation\Auth\User` (Authenticatable)
- Uses `HasFactory`, `Notifiable`
- `$fillable`: name, email, password
- **Relationships:**
  - `vehicles()` → `HasMany(Vehicle::class)`
  - `diagnostics()` → `HasMany(Diagnostic::class)`
- Note: `MustVerifyEmail` interface is commented out (email verification routes exist but the User model does not enforce it).

### `Vehicle` (`app/Models/Vehicle.php`)
- **Relationships:**
  - `user()` → `BelongsTo(User::class)`
  - `diagnostics()` → `HasMany(Diagnostic::class)`
- **Accessor:** `getDisplayNameAttribute()` — returns `nickname` if set, else `"year make model"`.
- `$casts`: year → integer, mileage_km → integer.

### `Diagnostic` (`app/Models/Diagnostic.php`)
- **Relationships:**
  - `user()` → `BelongsTo(User::class)`
  - `vehicle()` → `BelongsTo(Vehicle::class)`
- **Accessors:**
  - `getStatusLabelAttribute()` — human-readable status string
  - `getStatusBadgeAttribute()` — CSS class for colored badge (h-badge-*)
  - `getConfidencePercentAttribute()` — formats `ml_confidence` as "82.5%"
- **Helper methods:** `hasAudio()`, `hasVibration()`, `isReadyToSubmit()`
- `$casts`: ml_result_raw → array, ml_processed_at → datetime, vibration_peak_g/rms_g/ml_confidence → float.

**Relationship diagram:**
```
User ──< Vehicle ──< Diagnostic
User ──────────────< Diagnostic
```

**Evidence:** `app/Models/User.php`, `app/Models/Vehicle.php`, `app/Models/Diagnostic.php`

---

## 4. Controllers and Routes

### Route map (`routes/web.php`)

| Method | URI | Controller@method | Name | Auth? |
|---|---|---|---|---|
| GET | / | closure → view('welcome') | — | No |
| GET | /dashboard | closure → view('dashboard') | dashboard | auth+verified |
| GET | /profile | ProfileController@edit | profile.edit | auth+verified |
| PATCH | /profile | ProfileController@update | profile.update | auth+verified |
| DELETE | /profile | ProfileController@destroy | profile.destroy | auth+verified |
| GET | /vehicles/catalog/makes | VehicleLookupController@makes | vehicles.catalog.makes | auth+verified |
| GET | /vehicles/catalog/models | VehicleLookupController@models | vehicles.catalog.models | auth+verified |
| GET | /vehicles/catalog/vin | VehicleLookupController@vin | vehicles.catalog.vin | auth+verified |
| GET | /vehicles | VehicleController@index | vehicles.index | auth+verified |
| GET | /vehicles/create | VehicleController@create | vehicles.create | auth+verified |
| POST | /vehicles | VehicleController@store | vehicles.store | auth+verified |
| GET | /vehicles/{vehicle} | VehicleController@show | vehicles.show | auth+verified |
| GET | /vehicles/{vehicle}/edit | VehicleController@edit | vehicles.edit | auth+verified |
| PUT/PATCH | /vehicles/{vehicle} | VehicleController@update | vehicles.update | auth+verified |
| DELETE | /vehicles/{vehicle} | VehicleController@destroy | vehicles.destroy | auth+verified |
| GET | /diagnostics | DiagnosticController@index | diagnostics.index | auth+verified |
| GET | /diagnostics/create | DiagnosticController@create | diagnostics.create | auth+verified |
| POST | /diagnostics | DiagnosticController@store | diagnostics.store | auth+verified |
| GET | /diagnostics/{diagnostic} | DiagnosticController@show | diagnostics.show | auth+verified |
| DELETE | /diagnostics/{diagnostic} | DiagnosticController@destroy | diagnostics.destroy | auth+verified |
| POST | /diagnostics/{diagnostic}/analyze | DiagnosticController@analyze | diagnostics.analyze | auth+verified |
| GET | /diagnostics/{diagnostic}/report | DiagnosticController@report | diagnostics.report | auth+verified |

Auth routes (`routes/auth.php`) cover: register, login, logout, forgot-password, reset-password, verify-email, confirm-password, password update.

### Controller details

**`VehicleController`** — full resource CRUD. Authorization done inline via `authorizeOwner()` (abort 403 if `user_id !== Auth::id()`). Validation covers all vehicle fields.

**`DiagnosticController`** — most complex controller:
- `store()`: handles both `audio_blob` (live-recorded blob injected by JS `DataTransfer`) and `audio_file` (uploaded file), plus `vibration_file`. Calls `parseVibrationSummary()` to compute peak/RMS/sample-count on upload. Auto-sets status to `submitted` when both sources present, `draft` otherwise.
- `analyze()`: injects `MlBackendService`, marks status `processing`, calls `ml->analyze()`, saves results, returns human-readable flash message.
- `report()`: renders a standalone print-ready HTML page (not wrapped in app layout) at `diagnostics/{diagnostic}/report`.
- `destroy()`: deletes storage files before DB deletion.

**`VehicleLookupController`** — JSON API endpoints consumed by in-page JS. Three actions: `makes()`, `models()`, `vin()`.

**`ProfileController`** — standard Breeze profile (edit/update/destroy).

**Evidence:** `app/Http/Controllers/VehicleController.php`, `app/Http/Controllers/DiagnosticController.php`, `app/Http/Controllers/VehicleLookupController.php`, `routes/web.php`

---

## 5. Services

### `NhtsaVehicleService` (`app/Services/NhtsaVehicleService.php`)

Wraps the free [NHTSA vPIC API](https://vpic.nhtsa.dot.gov/api/) (no API key required).

**Methods:**
- `makes(): array` — fetches unique make names for `car`, `truck`, `mpv` vehicle types. Results cached in the database cache for **24 hours** under key `nhtsa.makes.v1`.
- `models(string $make, ?int $year): array` — fetches models for a make, optionally filtered by year. Cached **12 hours** per `make|year` combo.
- `decodeVin(string $vin): array` — decodes a 17-char VIN into `{vin, make, model, year, engine_type, transmission}`. Applies title-case corrections for known brands (BMW, GMC, Kia). Maps transmission style strings to the app's enum values.

Error handling: `ConnectionException` and non-2xx responses both throw `RuntimeException`. `InvalidArgumentException` thrown for malformed VINs. All errors logged via Laravel's `Log` facade.

**Config:** `config/services.php` → `services.nhtsa.url` / `.timeout` (env keys: `NHTSA_VPIC_URL`, `NHTSA_VPIC_TIMEOUT`).

### `MlBackendService` (`app/Services/MlBackendService.php`)

HTTP client for the FastAPI backend at `http://localhost:8001` (configurable via `ML_BACKEND_URL` env).

**Methods:**
- `isAlive(): bool` — GET `/health` with 3 s timeout.
- `analyze(Diagnostic $diagnostic): array` — posts files as multipart form to `POST /analyze`. Returns parsed JSON result.

**Graceful degradation:** If `isAlive()` returns false, or any network/server error occurs, the service falls back to `mockAnalysis()` — a rule-based PHP classifier that uses the vibration stats already stored on the Diagnostic model (`vibration_peak_g`, `vibration_rms_g`). The mock returns a realistic response including `mode: "mock_{reason}"` and a warnings array so the UI can show "Simulated result (ML server offline)".

Mock rules (simplified): high peak_g (>3.5) or crest factor (>4.5) → knocking; high crest with moderate peak → misfiring; high RMS with low peak → idling_rough; default → normal.

**Config:** `config/services.php` → `services.ml_backend.url` / `.timeout`.

**Evidence:** `app/Services/NhtsaVehicleService.php`, `app/Services/MlBackendService.php`, `config/services.php`

---

## 6. Views / Frontend

### Blade template tree

```
resources/views/
├── welcome.blade.php              # Public landing page (standalone HTML, no layout)
├── dashboard.blade.php            # Authenticated dashboard
├── layouts/
│   ├── app.blade.php              # Authenticated layout (sidebar + topbar)
│   ├── guest.blade.php            # Auth pages layout
│   └── navigation.blade.php      # Left sidebar nav component
├── components/                    # Breeze stock components (text-input, modal, etc.)
│   └── ...
├── auth/                          # Breeze auth views (login, register, reset, verify)
│   └── ...
├── profile/
│   ├── edit.blade.php
│   └── partials/
│       ├── update-profile-information-form.blade.php
│       ├── update-password-form.blade.php
│       └── delete-user-form.blade.php
├── vehicles/
│   ├── index.blade.php            # Grid of vehicle cards
│   ├── create.blade.php           # Add vehicle form
│   ├── edit.blade.php             # Edit vehicle form
│   ├── show.blade.php             # Vehicle detail + linked diagnostics
│   └── partials/
│       └── catalog-fields.blade.php  # Reusable VIN/make/model lookup UI + JS
└── diagnostics/
    ├── index.blade.php            # Paginated diagnostic history
    ├── create.blade.php           # 4-step wizard (vehicle → audio → vibration → review)
    ├── show.blade.php             # ML result panel + data cards
    └── report.blade.php           # Standalone print-ready PDF report
```

### Design system

A custom dark design system is defined entirely in `resources/css/app.css` using CSS custom properties (variables). Tailwind is included but the app primarily uses a hand-crafted class vocabulary (`haven-*`, `h-*` prefixes):

- Dark palette: `#111214` background, `#18191c` surface, teal accent (`#2dd4bf`)
- Component classes: buttons (`haven-btn-primary/secondary/ghost/danger`), form inputs (`haven-input`), badges (`h-badge-teal/amber/red/green/gray`), flash messages (`h-flash-success/warn/error`), sidebar/topbar layout.
- WCAG AA focus rings implemented globally via `:focus-visible`.

### JavaScript

- **Alpine.js v3** for reactive UI (registered globally in `resources/js/app.js`).
- **Inline `<script>` blocks** handle complex behaviors within Blade templates:
  - `catalog-fields.blade.php`: full NHTSA API integration — live cascade (make → model dropdowns with `fetch()`), VIN decode, "Other (type manually)" fallback.
  - `diagnostics/create.blade.php`: 4-step wizard state machine, Web Audio API `MediaRecorder` for live recording + waveform canvas visualizer, `DataTransfer` blob injection into hidden file input, vibration CSV client-side preview with peak/RMS calculation.
  - `layouts/app.blade.php`: sidebar open/close for mobile.

### Vite config

`vite.config.js` uses `laravel-vite-plugin` with inputs `resources/css/app.css` and `resources/js/app.js`. Hot reload enabled.

**Evidence:** `resources/views/**`, `resources/css/app.css`, `resources/js/app.js`, `vite.config.js`, `tailwind.config.js`

---

## 7. Auth System

Built on **Laravel Breeze v2** (session-based, no tokens/API auth).

**Full auth flow implemented:**
- Register / Login / Logout
- Forgot password → email → reset password
- Email verification (routes exist; the `User` model does NOT implement `MustVerifyEmail` — the interface is commented out, but the `verified` middleware is used on all protected routes, creating a mismatch — see §9)
- Password confirmation (for sensitive actions)
- Profile update (name, email) + password change + account deletion

All protected routes use `middleware(['auth', 'verified'])`. Resource ownership is enforced in controllers via manual `abort_if($record->user_id !== Auth::id(), 403)` checks — no Gate/Policy abstraction yet.

**Evidence:** `routes/auth.php`, `app/Http/Controllers/Auth/`, `app/Models/User.php`

---

## 8. AI / ML Integration Points

### FastAPI Backend (`ml_backend/`)

**`main.py`** — FastAPI app with three endpoints:
- `GET /health` — returns status, whether `model.pkl` is loaded, and mode (`trained` vs `rule_based`).
- `GET /info` — returns feature spec and class list.
- `POST /analyze` — receives `audio_file` (webm/wav/mp3) and/or `vibration_file` (CSV/JSON) as multipart form data alongside `diagnostic_id` / `vehicle_id` integers. Writes to temp files, extracts features, classifies, returns JSON.

**`features.py`** — Feature extraction:
- `extract_audio_features()`: uses `librosa` for RMS energy (mean/std), zero-crossing rate, spectral centroid, spectral rolloff, spectral bandwidth, 13 MFCCs (mean + std each). Falls back to file-size placeholder if librosa is unavailable.
- `extract_vibration_features()`: pure numpy/CSV. Accepts `timestamp,x,y,z` or `timestamp,magnitude` CSV, or equivalent JSON array. Computes peak_g, rms_g, mean_g, std_g, crest_factor, sample_count.

**`classifier.py`** — `EngineClassifier` class:
- On startup, attempts to load `model.pkl` (scikit-learn Pipeline saved as `{"model": ..., "meta": {...}}`).
- If found: uses `predict()` + `predict_proba()` for trained inference.
- If not found: falls back to a rule-based classifier with threshold logic covering all five condition classes.
- Returns `{condition, confidence, top_classes[3], mode}`.

**Five condition classes:** `normal`, `knocking`, `misfiring`, `idling_rough`, `overheating`.

**Laravel ↔ FastAPI integration** (`MlBackendService`):
- Sends stored audio and vibration files to `/analyze` as multipart.
- On any failure, falls back to PHP-side mock classifier.
- Results saved in `diagnostics.ml_condition`, `.ml_confidence`, `.ml_result_raw` (full JSON), `.ml_processed_at`.

**Status:** `model.pkl` does not yet exist in the repo. The backend runs in rule-based mode. No training pipeline or dataset is present.

**Evidence:** `ml_backend/main.py`, `ml_backend/classifier.py`, `ml_backend/features.py`, `ml_backend/requirements.txt`, `app/Services/MlBackendService.php`

---

## 9. Notably Missing / TODO

### Hard gaps (functionality not yet built)

| Gap | Evidence |
|---|---|
| **No trained ML model** — `model.pkl` absent. Both the FastAPI rule-based fallback and the Laravel PHP mock classifier produce heuristic results, not data-trained predictions. | `ml_backend/classifier.py` line: "model.pkl not found — using rule-based classifier." |
| **No training dataset or pipeline** — No scripts, notebooks, or data directories exist for model training. | Entire `ml_backend/` directory |
| **Email verification mismatch** — `User` model does NOT implement `MustVerifyEmail`, but all protected routes require `verified` middleware. New users are blocked from the app unless their email is manually verified or the middleware is removed. | `app/Models/User.php` (commented import), `routes/web.php` `middleware(['auth', 'verified'])` |
| **No Laravel Policies/Gates** — Authorization is done ad-hoc with `abort_if($record->user_id !== Auth::id(), 403)` in every controller. This works but won't scale, and there is no admin/role system. | `VehicleController.php`, `DiagnosticController.php` |
| **No queue job for ML analysis** — `DiagnosticController::analyze()` calls `MlBackendService::analyze()` synchronously in the HTTP request. For real audio files this will hit PHP's execution timeout. Queue infrastructure (jobs table) is set up but unused for ML. | `DiagnosticController.php` `analyze()` method |
| **Stage 7 / Stage 8 features** — "Learning Modules" section is placeholder in the sidebar with "S8" chip, disabled. No implementation exists. | `navigation.blade.php` `.haven-nav-item.disabled` |
| **No vibration re-upload / re-capture** — A diagnostic saved as `draft` (e.g., only audio captured) has no edit UI to attach missing files. Users must delete and recreate. | `routes/web.php` (no `update` on diagnostics resource), `DiagnosticController` (no `edit`/`update` methods) |
| **No admin panel** — No way to inspect all users, diagnostics, or system health from within the application. | `routes/web.php` |

### Minor gaps / improvements

| Issue | Detail |
|---|---|
| `tailwind.config.js` references `Figtree` font | Theme extends Figtree but the app loads `Inter` from Google Fonts. The Tailwind font-family config is effectively ignored. |
| `NhtsaVehicleService::titleCase()` hardcodes only 3 brand corrections | BMW, GMC, Kia. Other all-caps brands (FORD, DODGE, etc.) will render as "Ford", "Dodge" — inconsistent. |
| No test coverage for `DiagnosticController` or `MlBackendService` | Only `VehicleLookupTest` and Breeze-generated auth tests exist. No test for file upload, analyze endpoint, or mock classifier behavior. |
| `audio_duration_sec` is set from a JS hidden input | The value is sent by the client and trusted directly; no server-side validation or independent measurement. |
| `ML_BACKEND_URL` not present in `.env.example` | The env key is documented in `config/services.php` but missing from `.env.example`, making it easy to miss during setup. |
| CORS on FastAPI only allows `localhost:8000` | Hard-coded in `main.py`. Any deployment to a different URL or port requires a code change. |

---

## 10. Conclusions and Recommendations

### What is solid

The core data pipeline is complete and coherent:  
`Register → Add Vehicle (with NHTSA VIN/catalog lookup) → Create Diagnostic (live mic + ESP32 CSV upload) → Trigger ML Analysis → View Results + Print Report`

The UI is polished for a capstone project — a consistent dark design system, responsive layout, live waveform visualizer, client-side vibration preview, and a well-structured 4-step wizard. The `MlBackendService` fallback architecture means the app is demonstrable even without the Python server running.

### Priority recommendations

1. **Fix the email verification mismatch** (quick fix): Either uncomment `MustVerifyEmail` in `User.php` or remove `verified` from the route middleware group. Currently, newly registered users cannot access any protected page.

2. **Move ML analysis to a queue job** (important for reliability): Extract `$ml->analyze($diagnostic)` into a queued `AnalyzeDiagnosticJob`. The job table and queue driver are already configured. This prevents HTTP timeouts on large audio files and makes the UI non-blocking.

3. **Add a diagnostic edit route** (usability gap): Allow users to attach missing files to a `draft` diagnostic rather than forcing deletion and recreation.

4. **Train and integrate the real model**: The entire point of the project. The feature extraction pipeline is production-ready. The next step is to collect labeled engine recordings, train a scikit-learn (or PyTorch) classifier, and drop `model.pkl` into `ml_backend/`. The `classifier.py` loader will pick it up automatically.

5. **Add `ML_BACKEND_URL` to `.env.example`**: One-line fix to prevent confusion during dev setup.

6. **Introduce Laravel Policies** (`VehiclePolicy`, `DiagnosticPolicy`): Replace the `abort_if(...)` pattern for cleaner, testable authorization.

7. **Fix CORS in FastAPI**: Replace the hard-coded `localhost:8000` with an environment variable so the backend is portable.

---

*Report generated by automated codebase scan. All citations are to files under `c:\xampp\htdocs\Haven-Ai\`.*

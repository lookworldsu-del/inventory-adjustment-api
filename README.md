# Inventory Adjustment API

English | [Simplified Chinese](README.zh-CN.md)

A standalone Laravel API for adjusting warehouse batch quantities with predefined reasons. Each adjustment records the previous quantity, the counted quantity, their difference, a reason, and an optional note. The adjustment record and the batch quantity are saved in one database transaction.

Example: a batch has **100** units in the system. A physical count finds **92**. The API records **100 → 92**, a difference of **−8**, and the selected reason, then updates the batch quantity to **92**.

## Requirements

- **PHP 8.4.1–8.5.x**; verified with PHP 8.5.0. Although the root Composer constraint starts at PHP 8.3, the committed lock file includes Symfony packages requiring PHP 8.4.1 or later, and other locked packages currently support versions through PHP 8.5.
- **Composer 2**.
- **MySQL 8.4 / InnoDB**; verified with MySQL 8.4.11.
- PHP's **PDO MySQL extension** and the extensions required by Composer. The test suite also needs PHP CLI subprocess support (`proc_open`) for the concurrency test.

The project uses Laravel 13 and PHPUnit 12. API setup and tests do not require a frontend build, Node.js, Redis, or a queue worker.

## Setup

Run all commands from the project directory containing `artisan` and `composer.json`. These examples use a POSIX shell, such as macOS Terminal or Linux.

### 1. Install dependencies and create local configuration

On a fresh checkout:

```bash
composer install --no-interaction
composer check-platform-reqs
php --ri pdo_mysql
cp .env.example .env
php artisan key:generate --no-interaction
```

Keep an existing `.env` and application key when updating an already configured checkout. Install from `composer.lock` using `composer install` so the dependency versions match this project.

### 2. Create the databases

Connect to your MySQL server with an account allowed to create databases, then run:

```sql
CREATE DATABASE IF NOT EXISTS inventory_adjustment
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE DATABASE IF NOT EXISTS inventory_adjustment_testing
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Configure `.env` with an existing MySQL account that can read/write data and create, alter, and drop tables in these two schemas. `inventory_app` below is an example account name; the application does not create database users or grant permissions.

```dotenv
APP_URL=http://127.0.0.1:8001
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=inventory_adjustment
DB_USERNAME=inventory_app
DB_PASSWORD="your-local-database-password"
DB_URL=
DB_SOCKET=
```

Use your server's actual port, username, and password. The template uses MySQL's usual port **3306**; the author's existing local setup uses **13306**. Keep real credentials in `.env`, which is excluded from Git.

### 3. Migrate, seed, and start the API

```bash
php artisan config:clear --no-interaction
php artisan migrate --seed --no-interaction
php artisan serve --host=127.0.0.1 --port=8001 --no-interaction
```

Keep this terminal running. The API base URL is `http://127.0.0.1:8001/api/v1`; Laravel's health endpoint is `http://127.0.0.1:8001/up`.

Migrations create the five business tables and Laravel's standard support tables. The test runner manages migrations for the separate test database.

## Demo data

Fresh seeding creates **2 warehouses, 2 products, 2 batches, 6 reasons, and no adjustments**.

| Batch | Product | Warehouse | Initial quantity |
| --- | --- | --- | ---: |
| `BATCH-001` | `MOUSE-001` — Wireless Mouse | Shanghai Warehouse | 100 |
| `BATCH-002` | `KEYBOARD-001` — Mechanical Keyboard | Guangzhou Warehouse | 50 |

| Reason | Type | Active | Available for new adjustments |
| --- | --- | --- | --- |
| Physical count correction | `inventory_adjustment` | Yes | Yes |
| Damaged items | `inventory_adjustment` | Yes | Yes |
| Missing items | `inventory_adjustment` | Yes | Yes |
| Data entry correction | `inventory_adjustment` | Yes | Yes |
| Retired physical count reason | `inventory_adjustment` | No | No |
| Order cancellation | `order_cancellation` | Yes | No |

On a fresh, empty database, `BATCH-001` has ID `1` and the four usable reasons have IDs `1`–`4`. IDs may differ in an existing database; use the reasons endpoint and inspect the seeded batches when necessary.

Re-running `php artisan db:seed --no-interaction` does not duplicate the standard demo rows, reset adjusted quantities, or reactivate reasons that were manually disabled. It does not reset the demo to its initial state.

## API

All endpoints return JSON. Send `Accept: application/json`; POST requests also need `Content-Type: application/json`. Responses use a top-level `data` key for successful resources. Authentication is outside this standalone demo's scope; the endpoints are open.

| Method | Path | Successful response |
| --- | --- | --- |
| GET | `/api/v1/adjustment-reasons` | `200` — available reasons |
| POST | `/api/v1/inventory-adjustments` | `201` — created adjustment and relationships |
| GET | `/api/v1/inventory-adjustments/{inventoryAdjustment}` | `200` — adjustment and relationships |

### List available reasons

```bash
curl -sS http://127.0.0.1:8001/api/v1/adjustment-reasons \
  -H 'Accept: application/json'
```

```json
{
  "data": [
    {"id": 1, "name": "Physical count correction"},
    {"id": 2, "name": "Damaged items"},
    {"id": 3, "name": "Missing items"},
    {"id": 4, "name": "Data entry correction"}
  ]
}
```

Only active reasons with type `inventory_adjustment` are returned, ordered by ID. If none qualify, the response is `{"data":[]}`.

### Create an adjustment

`new_quantity` is the **absolute counted quantity**, not an amount to add or subtract.

| Field | Rules |
| --- | --- |
| `batch_id` | Required integer; batch must exist |
| `reason_id` | Required integer; reason must exist, be active, and have type `inventory_adjustment` |
| `new_quantity` | Required integer from `0` through `4294967295` |
| `note` | Optional, nullable string; at most 1,000 characters |

```bash
curl -i -X POST http://127.0.0.1:8001/api/v1/inventory-adjustments \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -d '{
    "batch_id": 1,
    "reason_id": 1,
    "new_quantity": 92,
    "note": "Morning physical count"
  }'
```

Example `201 Created` response for the first adjustment on a freshly seeded database:

```json
{
  "data": {
    "id": 1,
    "batch_id": 1,
    "reason_id": 1,
    "old_quantity": 100,
    "new_quantity": 92,
    "quantity_difference": -8,
    "note": "Morning physical count",
    "batch": {
      "id": 1,
      "batch_number": "BATCH-001",
      "quantity": 92,
      "product": {"id": 1, "name": "Wireless Mouse", "sku": "MOUSE-001"},
      "warehouse": {"id": 1, "name": "Shanghai Warehouse"}
    },
    "reason": {"id": 1, "name": "Physical count correction"}
  }
}
```

The server reads `old_quantity` from the locked batch and calculates `quantity_difference = new_quantity - old_quantity`. Client-supplied old quantities, differences, or adjustment IDs are ignored. Empty notes become `null`, and surrounding whitespace is trimmed.

### View an adjustment

Use the adjustment ID returned by POST:

```bash
curl -i http://127.0.0.1:8001/api/v1/inventory-adjustments/1 \
  -H 'Accept: application/json'
```

The response has the same structure as the creation response. `old_quantity`, `new_quantity`, and `quantity_difference` describe the historical adjustment; `batch.quantity` is the batch's **current** quantity and may have changed since then. A historical reason remains visible even if it is later disabled or assigned another type.

### Errors

- **422**: validation failed, including an unavailable reason or invalid quantity. No adjustment is created and stock is unchanged.
- **404**: the requested adjustment does not exist. A batch deleted between request validation and the transactional lookup also returns 404.
- An exception while writing the adjustment or stock causes the transaction to roll back. An unexpected exception is reported as a server error.

Example when `new_quantity` is `-1`:

```json
{
  "message": "The new quantity must be at least 0.",
  "errors": {
    "new_quantity": ["The new quantity must be at least 0."]
  }
}
```

Field names under `errors` identify the invalid inputs. The exact top-level error text can differ when several fields fail validation.

## Automated tests

Create `inventory_adjustment_testing` as described above, then run:

```bash
php artisan config:clear --no-interaction
php artisan test --compact
```

The suite uses **real MySQL**, including two independent PHP processes for the concurrency case. MySQL must be running; the development HTTP server is not needed.

`phpunit.xml` forces the MySQL connection and database name `inventory_adjustment_testing`. Host, port, and credentials come from the local environment. Both the base test class and concurrency workers verify the database before proceeding. Tests recreate or clear the dedicated test database, so it must contain only disposable test data. Run the suite serially: its fixed database name does not support `--parallel` or simultaneous test runs against the same database.

Verified result: **56 passing test cases, 312 assertions**, including 54 business cases and 2 framework examples.

| Test class | Coverage |
| --- | --- |
| `StoreInventoryAdjustmentTest` | Creation, increases/decreases/zero/unchanged quantities, numeric boundaries, optional notes, invalid inputs, English validation messages, unavailable reasons, server-calculated fields, successive adjustments, and isolation from other batches |
| `ListAdjustmentReasonsTest` | Active/type filtering, output fields, ordering, empty results |
| `ShowInventoryAdjustmentTest` | Related batch/product/warehouse/reason, 404, historical quantities and reasons |
| `InventoryAdjustmentTransactionTest` | Rollback after each write; revalidation when a reason or batch changes after initial validation, including English reason errors |
| `InventoryAdjustmentConcurrencyTest` | Two competing requests correctly produce `100 → 92 → 90` |
| `DemoSeederTest` | Complete seed data, valid relationships, repeat seeding without duplicates or stock resets |

To run only the creation tests:

```bash
php artisan test --compact --filter=StoreInventoryAdjustmentTest
```

## Design decisions

- **Five business tables.** A warehouse and product each have many batches; a batch belongs to one warehouse and one product. Adjustments belong to a batch and a predefined reason. Foreign keys restrict deletion of referenced rows.
- **Predefined reasons.** `type` determines where a reason can be used, and `is_active` controls availability for new adjustments. A free-text note supplements the reason; it cannot replace `reason_id`.
- **Atomic stock changes.** One transaction locks the selected batch with `lockForUpdate()`, reads its current quantity, inserts the adjustment, and updates stock. A write failure rolls back both changes.
- **Reason revalidation.** Inside that transaction, the selected reason is checked again and read with `sharedLock()`. This handles changes after initial validation and prevents the selected reason from being modified until the transaction ends.
- **Integer quantities.** Quantities use unsigned integers. The difference uses a signed `BIGINT` so both `0 → 4294967295` and the reverse can be recorded without overflow.
- **Simple Laravel structure.** Form Requests validate input; controllers coordinate database operations; Eloquent models express relationships; API Resources shape responses. Related data is explicitly loaded before serialization. No additional service or repository layer is needed for these three endpoints.
- **History and current state.** Adjustment quantities are stored snapshots. Related names and batch quantities are read from the current related records, rather than copied into a full historical snapshot. No endpoint edits or deletes adjustment records.
- **Absolute counts and repeated requests.** Submitting the current quantity is allowed and records a zero difference. Each POST creates a new adjustment; request deduplication/idempotency and stale-count detection are outside this demo. Row locking keeps the quantity history consistent, but does not decide which physical count is most recent.

## Project layout

```text
app/Http/Controllers/   API request handling
app/Http/Requests/      Input validation
app/Http/Resources/     JSON response representation
app/Models/            Eloquent models and relationships
database/migrations/   Database schema
database/seeders/      Repeatable demo data
database/factories/    Test data
routes/api.php         Versioned API routes
tests/Feature/         API, transaction, concurrency, and seeder tests
```

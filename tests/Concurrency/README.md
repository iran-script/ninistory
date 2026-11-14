# NiniStory PostgreSQL concurrency checks

These checks are intentionally kept separate from the SQLite PHPUnit suite. They must run against a disposable PostgreSQL database with at least two independent application/database processes.

## Environment

Set the normal Laravel PostgreSQL variables in a dedicated test environment, for example:

```powershell
$env:APP_ENV = 'testing'
$env:DB_CONNECTION = 'pgsql'
$env:DB_HOST = '127.0.0.1'
$env:DB_PORT = '5432'
$env:DB_DATABASE = 'ninistory_e2e'
$env:DB_USERNAME = 'ninistory_test'
$env:DB_PASSWORD = '<disposable-password>'
$env:PAYMENT_GATEWAY = 'mock'
$env:PAYMENT_MOCK_ENABLED = 'true'
```

Verify the database identity before running any destructive setup. Run migrations only on that disposable database:

```powershell
php artisan migrate --database=pgsql
php artisan test --testsuite=Feature --filter='Concurrency|InventoryReservation|PaymentInfrastructure'
```

The process-level scenarios still required for PostgreSQL validation are:

- two reservations against one final stock unit;
- overlapping multi-item reservations with deterministic variant lock ordering;
- duplicate checkout requests sharing one checkout key;
- verification versus reservation expiration;
- cancellation versus verification;
- duplicate payment verification.

The application now protects these paths with unique idempotency constraints, row locks, replay-safe reservation/movement writes, and server-side order/payment ownership checks. This environment currently has no `psql` executable and Docker is not running, so these process-level PostgreSQL checks were not claimed as executed in this run.

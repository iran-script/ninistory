# Payment development

NiniStory uses a provider-neutral payment boundary. The existing `payments` table stores independent attempts; `refunds` stores refund obligations. Confirmation always passes through `PaymentVerificationService`, then the existing `OrderInventoryService::confirmPayment()` commits inventory exactly once.

## Enable the local mock

In the local `.env` only:

```dotenv
PAYMENT_GATEWAY=mock
PAYMENT_MOCK_ENABLED=true
PAYMENT_ATTEMPT_TTL_MINUTES=15
PAYMENT_RECONCILIATION_ENABLED=true
```

The mock is rejected unless the environment is `local` or `testing` and the explicit flag is enabled. It never accepts card details and never transfers money.

## Test the flow

Create an order through checkout, then use the protected completion page and choose an action in **درگاه پرداخت آزمایشی نی‌نی استوری**. Success is verified through the central verification service and commits inventory once. Failed attempts remain in history and can be retried while the reservation is valid. Administrators can inspect `/admin/payments`.

Mock URLs are signed, expiring, attempt-bound, and POST actions require CSRF. Query-string success values cannot mark an order paid.

## Maintenance

```bash
php artisan payments:expire
php artisan payments:reconcile --dry-run
php artisan payments:reconcile
```

Mock expiration consults its internal test state. A future external adapter must query the provider before deciding that an ambiguous attempt failed. Reconciliation reports amount mismatches, incomplete verified transitions, and explicitly flagged attempts.

Paid administrative cancellation creates a refund obligation without restocking. Local-only mock refunds can be simulated from an admin payment detail page; this is not evidence that money moved.

## Adding a real provider

Implement `PaymentGatewayInterface`, register it in `PaymentGatewayManager`, keep credentials secret, implement server-side/signed verification and reconciliation, explicitly convert toman to the provider unit, and add contract tests for timeout, duplicate callbacks, refunds, and webhooks. Enable it only after production approval. Never enable the mock in production.

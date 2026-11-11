# Development demo catalog

NiniStory includes an opt-in catalog for local checkout, inventory, order-management, and mock-payment testing. It creates 12 hierarchical `demo-*` categories, 40 Persian products (15 girls, 15 boys, 10 baby), and 120 variants with deterministic SKUs.

Only local/testing environments are accepted, and the explicit flag is required. The safe seed command is:

```powershell
$env:DEMO_CATALOG_ENABLED='true'
php artisan demo:catalog --seed
```

The command is idempotent. It does not create customers, orders, payments, or fake financial transactions. Variant stock is initialized through `InventoryService`, so inventory movement history records demo stock setup. The catalog includes healthy stock, low stock, and out-of-stock variants. Use `DEMO-GIRLS-01-3` for an in-stock variant with at least 5 physical units; add quantity 2 to exercise checkout.

## End-to-end mock payment test

1. Run the safe catalog command above.
2. Set `PAYMENT_GATEWAY=mock` and `PAYMENT_MOCK_ENABLED=true` in the local `.env` only.
3. Add two units of `DEMO-GIRLS-01-3` to the cart.
4. Complete guest or authenticated checkout and confirm an active stock reservation exists.
5. Initiate payment from the protected checkout completion page.
6. Choose **پرداخت موفق آزمایشی** on the signed local mock gateway.
7. Confirm the order is paid, physical stock decreases by exactly 2, and reserved quantity becomes zero.
8. Inspect the payment attempt and `sale_fulfillment` inventory movement in admin.
9. Repeat verification; no second movement or deduction is created.
10. Repeat with separate orders using failed and cancelled mock outcomes; those attempts remain historical and do not commit stock.

## Images and cleanup

`public/images/ninistory/` contains generated promotional assets only (`hero-kidswear.webp`, banners, and the demo dress). They are not product photography and are not assigned to demo SKUs. Demo products therefore use the existing product-specific visual fallback, which produces no image URL or HTTP 404.

To safely clean later:

```powershell
$env:DEMO_CATALOG_ENABLED='true'
php artisan demo:catalog --clean
```

Products or variants referenced by orders, reservations, or inventory movements are preserved and deactivated. Unreferenced demo records can be removed; real records and inventory history are not targeted.

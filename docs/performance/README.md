# NiniStory performance workflow

The repeatable audit matrix is in `tests/performance/run-lighthouse.ps1`. It expects the application to be running at `http://127.0.0.1:8123` and uses Lighthouse 13.6.0 through isolated `npx` execution; it does not add a production dependency or install a global package.

```powershell
php artisan serve --host=127.0.0.1 --port=8123 --no-reload
powershell -ExecutionPolicy Bypass -File tests/performance/run-lighthouse.ps1
python tests/performance/check-budgets.py --results-dir .e2e/lighthouse/after --baseline-dir .e2e/lighthouse/baseline --budgets tests/performance/budgets.json --report docs/performance/after-summary.md
```

Reports are written under ignored `.e2e/lighthouse/` so JSON, traces, and screenshots cannot be committed accidentally. The matrix covers the homepage, catalog, major category, product detail, and article index in three mobile and three desktop runs. The median is used in the engineering report.

Lab measurements are controlled browser measurements, not field Core Web Vitals. Field INP and real-user LCP/CLS require production RUM or CrUX data after release.

## Performance budgets

Use these as review thresholds rather than single-run CI gates:

- Performance score median: 80 mobile / 90 desktop.
- LCP median: 2.5 seconds.
- CLS median: 0.1.
- Total blocking time median: 200 milliseconds.
- JavaScript transfer: 50 KB.
- Critical CSS transfer: 100 KB.

The small JavaScript bundle currently fits the JavaScript budget. Image budgets should be enforced by upload validation and generated derivatives rather than by hiding content from the browser.

The budget checker uses medians from three or more JSON runs, checks absolute page and asset budgets, and detects material regressions against the baseline. It intentionally exits non-zero when an after-run misses a budget. The measured comparison is documented in `docs/performance/comparison.md`.

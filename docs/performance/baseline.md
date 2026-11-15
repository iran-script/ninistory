# Phase 5.4 baseline

Collected 2026-11-14 with Lighthouse 13.6.0 against the local Laravel server at `127.0.0.1:8123`, production-built Vite assets, the existing SQLite fixture, and three comparable runs per page and mode. Lighthouse mobile uses its standard mobile emulation and throttling; desktop uses Lighthouse's desktop preset. Article detail was unavailable because the fixture has no published articles.

| Page | Mode | Performance | Accessibility | SEO | LCP ms | FCP ms | Speed Index ms | TBT ms | CLS | Bytes | Requests |
| --- | --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| Home | Mobile | 56 | 92 | 69 | 10,808 | 925 | 1,849 | 813 | 0.000 | 1,986,547 | 10 |
| Catalog | Mobile | 74 | 92 | 69 | 8,588 | 1,217 | 1,385 | 28 | 0.004 | 1,637,247 | 8 |
| Category | Mobile | 75 | 92 | 69 | 9,156 | 902 | 903 | 0 | 0.057 | 1,619,978 | 8 |
| Product | Mobile | 100 | 93 | 69 | 873 | 873 | 873 | 0 | 0.014 | 1,333,745 | 6 |
| Articles | Mobile | 100 | 95 | 100 | 851 | 851 | 851 | 0 | 0.005 | 1,326,734 | 6 |
| Home | Desktop | 92 | 92 | 69 | 1,848 | 242 | 365 | 0 | 0.008 | 1,986,547 | 10 |
| Catalog | Desktop | 94 | 88 | 69 | 1,687 | 301 | 525 | 0 | 0.007 | 1,637,247 | 8 |
| Category | Desktop | 95 | 92 | 69 | 1,507 | 243 | 304 | 0 | 0.007 | 1,619,978 | 8 |
| Product | Desktop | 100 | 93 | 69 | 236 | 236 | 298 | 0 | 0.009 | 1,333,745 | 6 |
| Articles | Desktop | 100 | 95 | 100 | 279 | 279 | 410 | 0 | 0.006 | 1,326,734 | 6 |

The largest transfer on the homepage was `public/brand-logo.png` at 1,134,798 bytes. The hero and fallback banners were WebP files between roughly 138 KB and 162 KB. The local fixture has no uploaded product images, so product-image optimization is verified by the upload pipeline and feature tests rather than by this page's baseline payload.

# Phase 5.4 Lighthouse comparison

The comparison uses the existing baseline reports and the final after reports under the ignored .e2e/lighthouse/ directory. Both matrices use Lighthouse 13.6.0, the local Laravel server at http://127.0.0.1:8123, production Vite assets, the same SQLite fixture, three runs per page and mode, Lighthouse mobile emulation/throttling, and the desktop preset. No baseline report was overwritten.

## Mobile

| Page | Performance before | Performance after | LCP before | LCP after | CLS before | CLS after | Transfer before | Transfer after |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| Home | 56 | 82 | 10,808 ms | 4,590 ms | 0.000 | 0.005 | 1,986,547 B | 873,369 B |
| Catalog | 74 | 91 | 8,588 ms | 3,385 ms | 0.004 | 0.004 | 1,637,247 B | 524,159 B |
| Category | 75 | 91 | 9,156 ms | 3,233 ms | 0.057 | 0.057 | 1,619,978 B | 506,890 B |
| Product | 100 | 99 | 873 ms | 753 ms | 0.014 | 0.014 | 1,333,745 B | 220,012 B |
| Articles | 100 | 100 | 851 ms | 873 ms | 0.005 | 0.005 | 1,326,734 B | 213,001 B |

## Desktop

| Page | Performance before | Performance after | LCP before | LCP after | CLS before | CLS after |
| --- | ---: | ---: | ---: | ---: | ---: | ---: |
| Home | 92 | 99 | 1,848 ms | 933 ms | 0.008 | 0.008 |
| Catalog | 94 | 100 | 1,687 ms | 711 ms | 0.007 | 0.007 |
| Category | 95 | 100 | 1,507 ms | 630 ms | 0.007 | 0.007 |
| Product | 100 | 100 | 236 ms | 240 ms | 0.009 | 0.009 |
| Articles | 100 | 100 | 279 ms | 232 ms | 0.006 | 0.006 |

## Findings

- The homepage logo transfer fell from 1,134,798 bytes (brand-logo.png) to 3,335 bytes over the network (brand-logo.webp), while the original PNG remains preserved as the source asset.
- Mobile homepage, catalog, and category payloads fell substantially because the oversized logo was removed from the critical request set.
- The after traces identify the homepage hero image as the LCP element. Its request is discoverable, eager, and high priority; server response time is approximately 40–100 ms and the hero image transfer is approximately 162 KB. Lighthouse still reports a 4.59 s median mobile LCP, so the 2.5 s target is not claimed as met.
- Catalog and category traces identify the first category-card image as LCP. It is now eager, high priority, and dimensioned. The measured median remains above 2.5 s, so this target is also not claimed as met.
- The CSS bundle remains render-blocking at approximately 80 KB in the final build. JavaScript remains approximately 2 KB. The font remains a single 111 KB variable WOFF2 with font-display: swap.
- These are local lab measurements. They do not certify field INP, field LCP/CLS, or Google rankings.

The budget checker intentionally fails the current after matrix for the three remaining mobile LCP misses and the homepage mobile performance score. This keeps the target visible instead of weakening the budget.

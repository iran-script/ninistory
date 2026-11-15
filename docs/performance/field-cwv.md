# Field Core Web Vitals collection

Local Lighthouse is lab data only. After the production domain is publicly reachable:

1. Run PageSpeed Insights for the production homepage, catalog, category, product, and article URLs. Record the mobile field data separately from the Lighthouse lab results.
2. Verify the domain in Google Search Console and review the Core Web Vitals report after enough real-user traffic has accumulated. Segment by mobile and desktop and investigate URL groups with failing LCP or CLS.
3. If faster feedback is needed, add production RUM using `PerformanceObserver` for LCP, CLS, and INP. Send anonymized measurements with the route, device class, connection type, and release identifier to the existing analytics or observability system. Do not send customer or order data.
4. Compare release medians and p75 values over time. Treat field INP, field LCP, and field CLS as separate from local Lighthouse scores; neither guarantees rankings.

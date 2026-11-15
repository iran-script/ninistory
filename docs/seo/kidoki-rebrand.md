# Kidoki SEO and launch notes

- Brand: Kidoki / کیدوکی
- Production canonical origin: `https://kidoki.ir`
- Instagram: `kidoki_shop`
- Market: all of Iran
- Business: children's clothing ecommerce

The application emits the production canonical origin only in the `production` environment. Local and staging requests keep their own URL and remain `noindex` unless both the application and the admin SEO setting explicitly enable indexing.

The original NiniStory logo files remain preserved as source/reference assets because no approved Kidoki logo was present in the workspace. Replace the storefront logo only after the approved Kidoki artwork is supplied; do not use generated or unrelated artwork.

Do not enable production indexing until real products and photographs, shipping/return/privacy policies, legal business details, DNS, HTTPS, and Search Console ownership are ready. Add the property `https://kidoki.ir`, submit `/sitemap.xml`, and validate canonical URLs, robots directives, Organization/WebSite/OnlineStore JSON-LD, and Product JSON-LD after deployment.

Local Lighthouse is laboratory data only. Collect field Core Web Vitals after launch using PageSpeed Insights, Search Console's Core Web Vitals report, or consent-compliant RUM. Lighthouse alone cannot certify field INP or rankings.

# NiniStory approved visual reference inventory

All originals are preserved. Dimensions are source PNG dimensions. Phone composites show devices on a presentation canvas; only the phone screen itself is the website UI. Desktop composite canvases also include paired screens for some routes.

| File | Dimensions | Page / breakpoint | Screen region | Laravel route |
| --- | ---: | --- | --- | --- |
| 01 - Initial NiniLand Illustrated Logo.png | 1254×1254 | Early logo concept | Full canvas | Shared brand |
| 02 - NiniStory Minimal Cloud Logo.png | 1254×1254 | Minimal logo concept | Full canvas | Shared brand |
| 03 - NiniStory Gold Emblem Logo Concept.png | 1254×1254 | Emblem concept | Full canvas | Shared brand |
| 04 - NiniStory Illustrated Kids Logo.png | 1254×1254 | Illustrated kids logo | Full canvas; two children, cloud wordmark, hanger | Shared header/brand |
| 05 - NiniStory Product Listing and Product Detail UI.png | 1199×1312 | Mobile catalog and product detail | Left phone = listing; right phone = detail | `/products`, `/products/{product:slug}` |
| 06 - NiniStory Homepage UI Approved.png | 1122×1402 | Mobile homepage | Full phone screen, outside pink presentation background excluded | `/` |
| 07 - NiniStory Cart and Checkout UI.png | 1199×1312 | Mobile cart and checkout | Left phone = cart; right phone = checkout | `/cart`, `/checkout` |
| 08 - NiniStory Account and Club UI.png | 1199×1312 | Mobile account and Club | Paired mobile panels | Planned account/Club routes |
| 09 - NiniStory Magic Fit UX Concept.png | 1536×1024 | Mobile Magic Fit concept | Mobile flow panels on canvas | Planned Magic Fit routes |
| 10 - NiniStory Desktop Homepage Concept.png | 1536×1024 | Desktop homepage | Full desktop canvas | `/` |
| 11 - NiniStory Desktop Catalog and Product Detail.png | 1536×1024 | Desktop catalog and product detail | Left = catalog; right = product detail | `/products`, `/products/{product:slug}` |
| 12 - NiniStory Desktop Cart and Checkout.png | 1536×1024 | Desktop cart and checkout | Left = cart; right = checkout | `/cart`, `/checkout` |
| 13 - NiniStory Desktop Account and Club.png | 1536×1024 | Desktop account/Club | Full desktop composite | Planned account/Club routes |
| 13 - NiniStory Desktop Account Club and Child Profiles.png | 1536×1024 | Desktop account, Club, profiles | Multiple desktop panels | Planned account/Club routes |
| 14 - NiniStory Desktop Magic Fit.png | 1536×1024 | Desktop Magic Fit | Full desktop composite | Planned Magic Fit routes |

## Visual mapping and key geometry

- Mobile homepage: warm pale-pink canvas; white rounded phone UI; compact three-part header (menu, teddy wordmark, favorites/cart), separate pill search; image-led rounded hero; three horizontal category tiles; two-column bestseller cards; wide seasonal campaign; paired Club/size guide cards; persistent five-item bottom navigation.
- Desktop homepage: narrow promo strip; three-row header with brand/actions/search and category navigation; wide rounded editorial hero; four horizontal category/campaign cards; service benefits strip; product grid beside Club promotion; generous white canvas.
- Mobile catalog/product detail: catalog banner and compact category chips/filters above a two-column image-led grid; product detail gallery precedes title, rating, color swatches, size chips, stock and full-width purchase CTA.
- Desktop catalog/product detail: catalog sidebar plus four-column cards; product gallery and details in balanced side-by-side columns, benefits strip below purchase controls.
- Cart/checkout: mobile stacked rounded rows and totals/CTA; checkout progress steps, recipient fields, address cards, shipping cards and summary. Desktop cart uses a broad item list with a narrow summary column; checkout uses form and summary columns.

## Asset findings

Reference screens contain editorial child/model and garment photography, seasonal campaign photos, product cutouts, category imagery and gift/size-guide illustrations. These are embedded in reference mockup screenshots and are not supplied as separate production media. Do not crop the mockup screens for storefront product imagery. The only separate current local brand asset is `../logo/NiniStory Pastel Cloud Logo.png`; `04 - NiniStory Illustrated Kids Logo.png` is an approved logo reference but is itself a concept image, not a confirmed transparent production logo asset.

## Supplied preview package

`NiniStory-visual-preview-assets.zip` was inspected and extracted to `../storage/app/demo-preview/`. Its own README identifies the JPGs as AI-generated composite crops, preview-only, and not SKU photography. The package's banners, category crops and product illustrations render only in Laravel `local` and `testing` environments through the guarded `preview.asset` route; the route returns 404 in other environments. Real `ProductImage` records take precedence over preview illustrations. Do not treat preview imagery as proof of stocked product appearance. The archive's `README.md` and `asset-manifest.json` remain beside the extracted assets.

Preview file mapping:

- Hero: `images/banners/hero-desktop.jpg`
- Seasonal campaign: `images/banners/autumn-collection.jpg`
- Categories: `images/categories/girls.jpg`, `boys.jpg`, `newborn.jpg`
- Demo product crop mapping: seeded cotton romper → `images/products/teddy-sweater.jpg`; sunny set → `floral-dress.jpg`; other demo slugs → `pink-coat.jpg` or `green-hoodie.jpg`.
- Club/size-guide panels: `images/banners/club.jpg`, `size-guide.jpg`.

Vazirmatn was not in the repository or local npm cache. The official npm install attempt was blocked by registry `EACCES`; the application therefore continues to use installed system Tahoma/Arial fallbacks until the official font package can be fetched and its SIL OFL license included.

from pathlib import Path
import re

from playwright.sync_api import Page, expect


ARTIFACTS = Path(__file__).resolve().parents[2] / ".e2e" / "artifacts"


def screenshot(page: Page, name: str) -> None:
    ARTIFACTS.mkdir(parents=True, exist_ok=True)
    page.screenshot(path=str(ARTIFACTS / name), full_page=True)


def test_storefront_responsive_smoke(page: Page) -> None:
    page.goto("http://127.0.0.1:8123/", wait_until="networkidle")
    expect(page).to_have_title(re.compile("کیدوکی"))
    expect(page.locator("main")).to_contain_text("پرفروش")
    expect(page.locator("img").first).to_be_visible()
    screenshot(page, "homepage-desktop.png")

    page.set_viewport_size({"width": 390, "height": 844})
    page.reload(wait_until="networkidle")
    expect(page.locator(".mobile-bottom-nav")).to_be_visible()
    expect(page.locator(".mobile-menu summary")).to_be_visible()
    expect(page.locator("body")).to_have_css("direction", "rtl")
    screenshot(page, "homepage-mobile-390.png")

    page.locator(".mobile-menu summary").click()
    expect(page.locator(".mobile-menu nav")).to_be_visible()


def test_guest_purchase_and_mock_payment(page: Page) -> None:
    page.goto("http://127.0.0.1:8123/products", wait_until="networkidle")
    product_link = page.locator(".product-card a").first
    expect(product_link).to_be_visible()
    product_link.click()
    page.wait_for_load_state("networkidle")
    screenshot(page, "product-detail.png")

    expect(page.locator("[data-variant-select]")).to_be_visible()
    expect(page.locator("[data-variant-price]")).not_to_have_text("—")
    available_variant_value = page.locator("[data-variant-select] option").evaluate_all("options => options.find(option => option.dataset.active === '1' && Number(option.dataset.stock) > 0)?.value")
    assert available_variant_value, "fixture must provide an active in-stock variant"
    page.locator("[data-variant-select]").select_option(available_variant_value)
    page.locator('input[name="quantity"]').fill("1")
    page.get_by_role("button", name="افزودن به سبد خرید").click()
    page.wait_for_load_state("networkidle")
    expect(page).to_have_url(re.compile(r"/cart"))
    screenshot(page, "cart.png")

    page.get_by_role("link", name="ادامه و ثبت نشانی").click()
    page.wait_for_load_state("networkidle")
    expect(page.locator("main form").first).to_be_visible()
    page.locator('input[name="recipient_name"]').fill("آزمایش نینی")
    page.locator('input[name="phone"]').fill("09120000000")
    page.locator('input[name="postal_code"]').fill("1234567890")
    page.locator('textarea[name="address"]').fill("خیابان آزمایش، پلاک ۱")
    page.locator('select[name="province_code"]').select_option(index=1)
    city = page.locator('select[name="city_code"] option').nth(1).get_attribute("value")
    page.locator('select[name="city_code"]').select_option(city)
    page.locator('input[name="shipping_method"]').first.check()
    screenshot(page, "checkout.png")
    page.get_by_role("button", name="ثبت سفارش برای پرداخت").click()
    page.wait_for_load_state("networkidle")
    expect(page).to_have_url(re.compile(r"/checkout/complete/"))
    screenshot(page, "checkout-complete.png")

    page.get_by_role("button", name="پرداخت آزمایشی").click()
    page.wait_for_load_state("networkidle")
    expect(page.locator("text=درگاه پرداخت آزمایشی")).to_be_visible()
    screenshot(page, "mock-payment.png")
    page.get_by_role("button", name="پرداخت موفق آزمایشی").click()
    page.wait_for_load_state("networkidle")
    expect(page.locator("text=پرداخت با موفقیت")).to_be_visible()
    screenshot(page, "payment-result.png")


def test_browser_security_smoke(page: Page) -> None:
    page.goto("http://127.0.0.1:8123/admin", wait_until="networkidle")
    expect(page).to_have_url(re.compile(r"/admin/login"))

    page.goto("http://127.0.0.1:8123/payments/00000000-0000-0000-0000-000000000000/mock", wait_until="networkidle")
    expect(page).to_have_title("Not Found")
    expect(page.locator("body")).not_to_contain_text("Whoops")


def test_browser_seo_smoke(page: Page) -> None:
    page.goto("http://127.0.0.1:8123/", wait_until="networkidle")
    expect(page.locator("meta[name='description']")).to_have_count(1)
    expect(page.locator("link[rel='canonical']")).to_have_count(1)
    expect(page.locator("meta[name='robots']")).to_have_attribute("content", re.compile("noindex"))
    schema_text = page.locator("script[type='application/ld+json']").first.evaluate("element => element.textContent")
    assert "Organization" in schema_text

    page.goto("http://127.0.0.1:8123/sitemap.xml", wait_until="networkidle")
    expect(page.locator("body")).to_contain_text("sitemapindex")


def test_live_search_autocomplete_keyboard_and_empty_state(page: Page) -> None:
    page.goto("http://127.0.0.1:8123/products", wait_until="networkidle")
    product_name = page.locator(".product-card h3").first.inner_text()
    query = product_name[:3]
    search = page.locator("#desktop-search")
    search.fill(query)
    expect(page.locator("#desktop-search-results")).to_be_visible()
    expect(page.locator("#desktop-search-results [role='option']").last).to_be_visible()
    search.press("Escape")
    expect(page.locator("#desktop-search-results")).to_be_hidden()

    search.fill("query-with-no-matching-products-xyz")
    expect(page.locator("#desktop-search-results")).to_contain_text("محصولی پیدا نشد")


def test_official_kidoki_logo_assets_are_served(page: Page) -> None:
    page.goto("http://127.0.0.1:8123/", wait_until="networkidle")
    for asset_url in [
        "/brand-logo.webp",
        "/images/brand/logo-512.webp",
        "/images/brand/favicon-32.png",
        "/images/brand/apple-touch-icon.png",
    ]:
        response = page.request.get(f"http://127.0.0.1:8123{asset_url}")
        assert response.status == 200, asset_url

    assert page.locator("img[alt='نشان کیدوکی']").count() >= 2

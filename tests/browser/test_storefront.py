from pathlib import Path
import re

from playwright.sync_api import Page, expect


ARTIFACTS = Path(__file__).resolve().parents[2] / ".e2e" / "artifacts"


def screenshot(page: Page, name: str) -> None:
    ARTIFACTS.mkdir(parents=True, exist_ok=True)
    page.screenshot(path=str(ARTIFACTS / name), full_page=True)


def test_storefront_responsive_smoke(page: Page) -> None:
    page.goto("http://127.0.0.1:8123/", wait_until="networkidle")
    expect(page).to_have_title(re.compile("نینی"))
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
    page.locator("[data-size]").first.click()
    page.locator('input[name="quantity"]').fill("2")
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

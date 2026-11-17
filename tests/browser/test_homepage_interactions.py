import pytest
from playwright.sync_api import Page, expect


BASE_URL = "http://127.0.0.1:8123"


def test_homepage_slider_interactions(page: Page) -> None:
    console_errors = []
    page.on("console", lambda message: console_errors.append(message.text) if message.type == "error" else None)
    page.goto(f"{BASE_URL}/", wait_until="networkidle")

    slider = page.locator("[data-campaign-slider]")
    expect(slider).to_be_visible()
    slides = slider.locator("[data-campaign-slide]")
    slide_count = slides.count()
    if slide_count < 2:
        expect(page.locator("[data-campaign-diagnostic]")).to_contain_text(str(slide_count))
        pytest.skip(f"Only {slide_count} eligible hero slide(s) are configured; add a second active Hero banner first.")
    assert "is-active" in (slides.nth(0).get_attribute("class") or "")
    slider.locator("[data-campaign-next]").click()
    assert "is-active" in (slides.nth(1).get_attribute("class") or "")
    slider.locator("[data-campaign-dot='0']").click()
    assert "is-active" in (slides.nth(0).get_attribute("class") or "")


def test_homepage_desktop_menu_interactions(page: Page) -> None:
    console_errors = []
    page.on("console", lambda message: console_errors.append(message.text) if message.type == "error" else None)
    page.goto(f"{BASE_URL}/", wait_until="networkidle")

    trigger = page.locator("[data-mega-trigger]").first
    expect(trigger).to_be_visible()
    trigger.click()
    menu = page.locator("[data-mega-menu]").first
    expect(menu).to_be_visible()
    expect(trigger).to_have_attribute("aria-expanded", "true")
    page.keyboard.press("Escape")
    expect(menu).to_be_hidden()
    expect(trigger).to_have_attribute("aria-expanded", "false")

    page.locator("main").click(position={"x": 10, "y": 10})
    assert not console_errors, f"Browser console errors: {console_errors}"


def test_mobile_category_drawer_opens_and_closes(page: Page) -> None:
    page.set_viewport_size({"width": 390, "height": 844})
    page.goto(f"{BASE_URL}/", wait_until="networkidle")

    opener = page.locator("[data-mobile-menu-open]").first
    opener.click()
    drawer = page.locator("[data-mobile-menu]")
    assert "is-open" in (drawer.get_attribute("class") or "")
    page.keyboard.press("Escape")
    assert "is-open" not in (drawer.get_attribute("class") or "")


def test_navigation_drilldown_reaches_available_depth(page: Page) -> None:
    page.goto(f"{BASE_URL}/", wait_until="networkidle")
    trigger = page.locator("[data-mega-trigger]").first
    expect(trigger).to_be_visible()
    trigger.click()
    menu = page.locator("[data-mega-menu]").first
    depth = 1
    while True:
        level = menu.locator(".mega-menu__children").last
        drill = level.locator(".mega-menu__enter").first
        if drill.count() == 0:
            break
        drill.click()
        depth += 1
        expect(level.locator(".mega-menu__list")).to_be_visible()
        if depth >= 5:
            break
    if depth < 5:
        pytest.skip(f"Only {depth} navigation level(s) are configured in the local catalog.")
    assert menu.locator(".mega-menu__path strong").text_content()


def test_mobile_navigation_uses_progressive_levels(page: Page) -> None:
    page.set_viewport_size({"width": 390, "height": 844})
    page.goto(f"{BASE_URL}/", wait_until="networkidle")
    page.locator("[data-mobile-menu-open]").first.click()
    drawer = page.locator("[data-mobile-navigation]")
    rows = drawer.locator(".mobile-nav__row")
    expect(rows.first).to_be_visible()
    enter = rows.locator(".mobile-nav__enter").first
    if enter.count() == 0:
        pytest.skip("No child category is configured in the local catalog.")
    enter.click()
    expect(drawer.locator(".mobile-nav__back")).to_be_visible()
    expect(drawer.locator(".mobile-nav__all")).to_be_visible()

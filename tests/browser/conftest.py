import pytest
from pathlib import Path
from playwright.sync_api import sync_playwright


@pytest.fixture
def page():
    with sync_playwright() as playwright:
        chrome = Path(r"C:\Program Files\Google\Chrome\Application\chrome.exe")
        browser = playwright.chromium.launch(headless=True, executable_path=str(chrome) if chrome.exists() else None)
        context = browser.new_context(locale="fa-IR", viewport={"width": 1440, "height": 900})
        page = context.new_page()
        yield page
        context.close()
        browser.close()

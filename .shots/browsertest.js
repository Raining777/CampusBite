'use strict';
// Real-browser regression tests for the review findings:
// F1 deadlock (confirm cancel), F4 named-button serialization, F2 counts, F3 padding, F5 placeholder.
const { chromium } = require('playwright-core');

const BASE = 'http://127.0.0.1:8080/index.php?page=';
const results = [];
const ok = (name, pass, detail = '') => results.push({ name, pass, detail });

(async () => {
    const browser = await chromium.launch({ channel: 'chrome', headless: true });
    const ctx = await browser.newContext({ viewport: { width: 1440, height: 1040 } });
    const page = await ctx.newPage();
    page.setDefaultTimeout(15000);

    // --- Vendor: named-button serialization (publish/hide) ---
    await page.goto(BASE + 'login', { waitUntil: 'networkidle' });
    await page.fill('[name=identifier]', 'northhall');
    await page.fill('[name=password]', 'CampusDemo!2026');
    await page.click('button[type=submit]');
    await page.waitForURL(/page=vendor-orders/);
    await page.goto(BASE + 'vendor-menu', { waitUntil: 'networkidle' });

    // Hide the first item (button name="operation" value="hide")
    await page.click('form:has(button[value=hide]) button[value=hide]');
    await page.waitForURL(/page=vendor-menu/);
    let html = await page.content();
    ok('hide button: item hidden notice', html.includes('Item hidden from the student menu.'), '');
    ok('hide button: badge shows hidden', html.includes('status-badge status-hidden'));

    // Publish it again (button name="operation" value="publish")
    await page.click('form:has(button[value=publish]) button[value=publish]');
    await page.waitForURL(/page=vendor-menu/);
    html = await page.content();
    ok('publish button: item published notice', html.includes('Item published.'), '');

    // --- Confirm dialog: cancel must NOT deadlock the button ---
    page.once('dialog', (d) => d.dismiss());
    await page.click('form[data-confirm] button[type=submit]');
    await page.waitForTimeout(400);
    const state = await page.evaluate(() => {
        const btn = document.querySelector('form[data-confirm] button[type=submit]');
        return { disabled: btn.disabled, busy: btn.classList.contains('is-busy'), submitting: document.querySelector('form[data-confirm]').dataset.submitting };
    });
    ok('confirm cancel: button still enabled', !state.disabled && !state.busy && !state.submitting, JSON.stringify(state));
    const rowsBefore = await page.$$eval('.data-table tbody tr', (els) => els.length);

    // Confirm dialog: accept must remove the item
    page.once('dialog', (d) => d.accept());
    await page.click('form[data-confirm] button[type=submit]');
    await page.waitForURL(/page=vendor-menu/);
    await page.waitForTimeout(400);
    const rowsAfter = await page.$$eval('.data-table tbody tr', (els) => els.length);
    ok('confirm accept: item removed', rowsAfter === rowsBefore - 1, `${rowsBefore} -> ${rowsAfter}`);

    // --- Vendor orders: All orders count now visible ---
    await page.goto(BASE + 'vendor-orders', { waitUntil: 'networkidle' });
    html = await page.content();
    ok('vendor filter: All orders shows total', /All orders \(\d+\)/.test(html));

    // --- Admin: enable/disable serialization ---
    await page.goto(BASE + 'menu', { waitUntil: 'networkidle' });
    await page.click('form.inline-form button[type=submit]'); // sign out
    await page.waitForURL(/page=menu/);
    await page.goto(BASE + 'login', { waitUntil: 'networkidle' });
    await page.fill('[name=identifier]', 'campusadmin');
    await page.fill('[name=password]', 'CampusDemo!2026');
    await page.click('button[type=submit]');
    await page.waitForURL(/page=admin-students/);

    await page.click('form:has(button[value="0"]) button[type=submit]'); // Disable student1
    await page.waitForURL(/page=admin-students/);
    html = await page.content();
    ok('admin: disable shows notice', html.includes('Account disabled.'), '');
    ok('admin: student1 badge disabled', /status-badge status-disabled/.test(html));

    await page.click('form:has(button[value="1"]) button[type=submit]'); // Enable student1
    await page.waitForURL(/page=admin-students/);
    html = await page.content();
    ok('admin: enable shows notice', html.includes('Account enabled.'), '');

    // --- Padding restored ---
    const pad = await page.evaluate(() => {
        const s = getComputedStyle(document.querySelector('.main-content'));
        return { top: s.paddingTop, bottom: s.paddingBottom };
    });
    ok('main content vertical padding restored', parseFloat(pad.top) > 20 && parseFloat(pad.bottom) > 40, JSON.stringify(pad));

    // --- Placeholder contrast ---
    await page.goto(BASE + 'menu', { waitUntil: 'networkidle' });
    const ph = await page.evaluate(() => {
        const el = document.querySelector('#menu-search');
        return { color: getComputedStyle(el, '::placeholder').color, opacity: getComputedStyle(el, '::placeholder').opacity };
    });
    ok('placeholder at full opacity', ph.opacity === '1', JSON.stringify(ph));

    await browser.close();
    const failed = results.filter((r) => !r.pass);
    for (const r of results) console.log(`${r.pass ? 'PASS' : 'FAIL'}  ${r.name}${r.detail ? '  → ' + r.detail : ''}`);
    console.log(`\n${results.length - failed.length}/${results.length} passed`);
    process.exit(failed.length ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(1); });

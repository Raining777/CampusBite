'use strict';
// CampusBite screenshot tool — drives system Chrome via playwright-core.
// Usage: node shots.js <outdir>
const { chromium } = require('playwright-core');
const fs = require('fs');
const path = require('path');

const OUT = process.argv[2] || '.';
const BASE = 'http://127.0.0.1:8080/index.php?page=';

async function shot(page, name, { mobile = false } = {}) {
    const file = path.join(OUT, name + '.png');
    await page.screenshot({ path: file, fullPage: false });
    console.log('shot:', name);
}

(async () => {
    const browser = await chromium.launch({ channel: 'chrome', headless: true });
    fs.mkdirSync(OUT, { recursive: true });

    const desktop = await browser.newContext({ viewport: { width: 1440, height: 1040 }, deviceScaleFactor: 1 });
    const page = await desktop.newPage();
    page.setDefaultTimeout(15000);

    // Guest pages
    await page.goto(BASE + 'menu', { waitUntil: 'networkidle' });
    await shot(page, 'guest-menu');
    await page.goto(BASE + 'login', { waitUntil: 'networkidle' });
    await shot(page, 'guest-login');
    await page.goto(BASE + 'register', { waitUntil: 'networkidle' });
    await shot(page, 'guest-register');

    // Student flow
    await page.goto(BASE + 'login', { waitUntil: 'networkidle' });
    await page.fill('[name=identifier]', 'student1');
    await page.fill('[name=password]', 'CampusDemo!2026');
    await page.click('button[type=submit]');
    await page.waitForURL(/page=menu/, { timeout: 15000 });
    await shot(page, 'student-menu');
    await page.goto(BASE + 'cart', { waitUntil: 'networkidle' });
    await shot(page, 'student-cart');
    await page.goto(BASE + 'checkout', { waitUntil: 'networkidle' });
    await shot(page, 'student-checkout');
    await page.goto(BASE + 'orders', { waitUntil: 'networkidle' });
    await shot(page, 'student-orders');
    await page.goto(BASE + 'order&id=1', { waitUntil: 'networkidle' });
    await shot(page, 'student-order');

    // Vendor flow
    await page.click('form.inline-form button[type=submit]'); // sign out
    await page.waitForURL(/page=menu/);
    await page.goto(BASE + 'login', { waitUntil: 'networkidle' });
    await page.fill('[name=identifier]', 'northhall');
    await page.fill('[name=password]', 'CampusDemo!2026');
    await page.click('button[type=submit]');
    await page.waitForURL(/page=vendor-orders/);
    await shot(page, 'vendor-orders');
    await page.goto(BASE + 'order&id=1', { waitUntil: 'networkidle' });
    await shot(page, 'vendor-order');
    await page.goto(BASE + 'vendor-menu', { waitUntil: 'networkidle' });
    await shot(page, 'vendor-menu');
    await page.goto(BASE + 'vendor-item', { waitUntil: 'networkidle' });
    await shot(page, 'vendor-item-form');
    await page.goto(BASE + 'vendor-categories', { waitUntil: 'networkidle' });
    await shot(page, 'vendor-categories');

    // Admin flow
    await page.click('form.inline-form button[type=submit]');
    await page.waitForURL(/page=menu/);
    await page.goto(BASE + 'login', { waitUntil: 'networkidle' });
    await page.fill('[name=identifier]', 'campusadmin');
    await page.fill('[name=password]', 'CampusDemo!2026');
    await page.click('button[type=submit]');
    await page.waitForURL(/page=admin-students/);
    await shot(page, 'admin-students');
    await page.goto(BASE + 'admin-kitchens', { waitUntil: 'networkidle' });
    await shot(page, 'admin-kitchens');
    await page.goto(BASE + 'admin-vendor-accounts', { waitUntil: 'networkidle' });
    await shot(page, 'admin-vendor-accounts');
    await desktop.close();

    // Mobile pass
    const mobile = await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true });
    const mpage = await mobile.newPage();
    mpage.setDefaultTimeout(15000);
    await mpage.goto(BASE + 'menu', { waitUntil: 'networkidle' });
    await shot(mpage, 'mobile-menu');
    await mpage.goto(BASE + 'login', { waitUntil: 'networkidle' });
    await mpage.fill('[name=identifier]', 'student1');
    await mpage.fill('[name=password]', 'CampusDemo!2026');
    await mpage.click('button[type=submit]');
    await mpage.waitForURL(/page=menu/, { timeout: 15000 });
    await shot(mpage, 'mobile-student-menu');
    await mpage.goto(BASE + 'cart', { waitUntil: 'networkidle' });
    await shot(mpage, 'mobile-cart');
    await mobile.close();

    await browser.close();
    console.log('done');
})().catch((e) => { console.error(e); process.exit(1); });

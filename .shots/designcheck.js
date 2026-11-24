'use strict';
// Design & smoke checks for CampusBite
const { chromium } = require('playwright-core');

const BASE = 'http://127.0.0.1:8080/index.php?page=';
const results = [];
const ok = (name, pass, detail = '') => results.push({ name, pass, detail });

function parseColor(s) {
    if (s.startsWith('#')) {
        const h = s.slice(1);
        const parts = h.length === 3 ? h.split('').map((c) => c + c) : h.match(/.{2}/g);
        return parts.map((x) => parseInt(x, 16));
    }
    return s.match(/[\d.]+/g).slice(0, 3).map(Number);
}
function lum(rgb) {
    const [r, g, b] = parseColor(rgb).map((v) => {
        v /= 255;
        return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
    });
    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}
function contrast(a, b) {
    const [l1, l2] = [lum(a), lum(b)].sort((x, y) => y - x);
    return (l1 + 0.05) / (l2 + 0.05);
}

(async () => {
    const browser = await chromium.launch({ channel: 'chrome', headless: true });
    const ctx = await browser.newContext({ viewport: { width: 1440, height: 1040 } });
    const page = await ctx.newPage();
    const consoleErrors = [];
    page.on('console', (m) => { if (m.type() === 'error') consoleErrors.push(m.text()); });
    page.on('pageerror', (e) => consoleErrors.push(String(e)));

    await page.goto(BASE + 'menu', { waitUntil: 'networkidle' });

    // Fonts actually loaded
    await page.evaluate(() => document.fonts.ready);
    ok('fonts: Fraunces loaded', await page.evaluate(() => document.fonts.check('500 32px Fraunces')));
    ok('fonts: Public Sans loaded', await page.evaluate(() => document.fonts.check('600 16px "Public Sans"')));

    // Layout sanity
    const noHOverflow = await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1);
    ok('no horizontal overflow (desktop)', noHOverflow);

    // Sticky header
    const headerPos = await page.$eval('.site-header', (el) => getComputedStyle(el).position);
    ok('header is sticky', headerPos === 'sticky', headerPos);

    // Hero typography
    const heroFont = await page.$eval('.hero h1', (el) => getComputedStyle(el).fontFamily);
    ok('hero uses Fraunces', heroFont.includes('Fraunces'), heroFont);

    // Card radius + shadow tokens
    const cardRadius = await page.$eval('.menu-card', (el) => getComputedStyle(el).borderRadius);
    ok('menu card radius applied', cardRadius === '16px', cardRadius);

    // Contrast checks (WCAG AA pairs)
    const checks = [
        ['body text on canvas', 'body', '--canvas'],
        ['muted text on surface', '.dish-meta', '--surface'],
        ['nav link (muted) on header surface', '.site-nav a', '--surface'],
        ['primary button text', '.button-primary', '--forest'],
        ['hero body copy on forest-deep', '.hero p', '--forest-deep'],
        ['footer text on forest-deep', '.footer-inner', '--forest-deep'],
    ];
    for (const [name, sel, token] of checks) {
        const pair = await page.evaluate(([s, t]) => {
            const el = document.querySelector(s);
            if (!el) return null;
            const fg = getComputedStyle(el).color;
            const bg = getComputedStyle(document.documentElement).getPropertyValue(t).trim();
            return { fg, bg };
        }, [sel, token]);
        if (pair) {
            const c = contrast(pair.fg, pair.bg);
            ok(`contrast: ${name}`, c >= 4.5, c.toFixed(2));
        } else {
            ok(`contrast: ${name}`, false, 'selector missing');
        }
    }

    // Favicon present
    const favicon = await page.$eval('link[rel="icon"]', (el) => el.getAttribute('href') || '').catch(() => '');
    ok('favicon present', favicon.startsWith('data:image/svg+xml'));

    // Grain overlay exists
    ok('grain overlay exists', await page.evaluate(() => {
        const s = getComputedStyle(document.body, '::after');
        return s.position === 'fixed' && s.backgroundImage.includes('svg');
    }));

    // Status badges have dots
    const badgeDot = await page.evaluate(() => {
        const b = document.querySelector('.status-badge');
        return b ? getComputedStyle(b, '::before').width === '6.5px' : null;
    }).catch(() => null);
    ok('status badge dot (menu page has none until orders)', badgeDot === null || badgeDot === true, badgeDot === null ? 'not on this page' : '');

    // Console errors on guest pages
    for (const p of ['menu', 'login', 'register']) {
        await page.goto(BASE + p, { waitUntil: 'networkidle' });
    }
    ok('no console errors on guest pages', consoleErrors.length === 0, consoleErrors.join(' | '));

    // Mobile pass
    const mobile = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
    const mp = await mobile.newPage();
    const mErrors = [];
    mp.on('console', (m) => { if (m.type() === 'error') mErrors.push(m.text()); });
    mp.on('pageerror', (e) => mErrors.push(String(e)));
    await mp.goto(BASE + 'menu', { waitUntil: 'networkidle' });
    ok('no horizontal overflow (mobile 390px)', await mp.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1));
    ok('mobile: hero visible', await mp.$eval('.hero', (el) => el.offsetHeight > 300));
    await mp.goto(BASE + 'register', { waitUntil: 'networkidle' });
    ok('mobile: register fits', await mp.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1));
    ok('no console errors on mobile pages', mErrors.length === 0, mErrors.join(' | '));

    await browser.close();
    const failed = results.filter((r) => !r.pass);
    for (const r of results) console.log(`${r.pass ? 'PASS' : 'FAIL'}  ${r.name}${r.detail ? '  → ' + r.detail : ''}`);
    console.log(`\n${results.length - failed.length}/${results.length} passed`);
    process.exit(failed.length ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(1); });

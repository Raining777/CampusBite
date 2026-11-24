'use strict';
// CampusBite end-to-end functional tests (server-side, via shared cookie jar)
const { chromium } = require('playwright-core');

const BASE = 'http://127.0.0.1:8080/index.php';
const results = [];
const ok = (name, pass, detail = '') => results.push({ name, pass, detail });

async function getForm(page) {
    const html = await (await page.request.get(BASE + '?page=register')).text();
    const csrf = (html.match(/name="csrf" value="([a-f0-9]{64})"/) || [])[1];
    return { csrf };
}

async function post(page, fields) {
    return page.request.post(BASE, { form: fields, maxRedirects: 0 });
}

async function login(page, identifier, password) {
    const html = await (await page.request.get(BASE + '?page=login')).text();
    const csrf = (html.match(/name="csrf" value="([a-f0-9]{64})"/) || [])[1];
    const res = await post(page, { csrf, action: 'login', identifier, password });
    return res;
}
async function signedInHome(page) {
    const html = await (await page.request.get(BASE + '?page=menu')).text();
    return html.includes('Sign out');
}


async function signOut(page) {
    const html = await (await page.request.get(BASE + '?page=menu')).text();
    const csrf = (html.match(/name="csrf" value="([a-f0-9]{64})"/) || [])[1];
    return post(page, { csrf, action: 'logout' });
}

(async () => {
    const browser = await chromium.launch({ channel: 'chrome', headless: true });
    const ctx = await browser.newContext();
    const page = await ctx.newPage();
    page.setDefaultTimeout(15000);

    // 1. Guest access control
    let res = await page.request.get(BASE + '?page=orders', { maxRedirects: 0 });
    ok('guest blocked from orders (303 → login)', res.status() === 303 && res.headers().location.includes('page=login'), res.status() + ' ' + res.headers().location);

    // 2. CSRF rejected
    res = await post(page, { action: 'login', identifier: 'student1', password: 'CampusDemo!2026' });
    ok('missing CSRF rejected', res.status() === 303, 'redirect to render error');

    // 3. Login failures
    res = await login(page, 'student1', 'wrong-password-123');
    const wrongPwPage = await (await page.request.get(BASE + '?page=login')).text();
    ok('wrong password rejected with error notice', res.status() === 303 && wrongPwPage.includes('incorrect'));
    res = await login(page, 'student1', 'CampusDemo!2026');
    ok('demo login ok', res.status() === 303 && res.headers().location.includes('page=menu'), res.headers().location);

    // 4. Registration validations (logged-in student must sign out first)
    res = await signOut(page);
    let { csrf } = await getForm(page);
    const baseUser = { action: 'register', username: 'validuser', email: 'valid@example.com', password: 'Passw0rd!x', confirm_password: 'Passw0rd!x' };

    res = await post(page, { ...baseUser, csrf, username: 'ab' });
    ok('username too short rejected', res.status() === 303);
    res = await post(page, { ...baseUser, csrf, username: 'bad name!' });
    ok('username illegal chars rejected', res.status() === 303);
    res = await post(page, { ...baseUser, csrf, email: 'not-an-email' });
    ok('invalid email rejected', res.status() === 303);
    res = await post(page, { ...baseUser, csrf, password: 'short1!' });
    ok('weak password rejected', res.status() === 303);
    res = await post(page, { ...baseUser, csrf, password: 'Nodigit!here' });
    ok('password without digit rejected', res.status() === 303);
    res = await post(page, { ...baseUser, csrf, password: 'nodigit123456', confirm_password: 'nodigit123456' });
    ok('password without special char rejected', res.status() === 303);
    res = await post(page, { ...baseUser, csrf, confirm_password: 'different' });
    ok('mismatched confirm rejected', res.status() === 303);
    res = await post(page, { ...baseUser, csrf, email: 'student1@campusbite.test' });
    ok('duplicate email rejected', res.status() === 303);
    res = await post(page, { ...baseUser, csrf, username: 'student1' });
    ok('duplicate username rejected', res.status() === 303);

    // 5. Successful registration → login
    res = await post(page, { ...baseUser, csrf });
    ok('registration ok (303 → login)', res.status() === 303 && res.headers().location.includes('page=login'));
    res = await login(page, 'validuser', 'Passw0rd!x');
    ok('new account can sign in', res.status() === 303 && res.headers().location.includes('page=menu'));

    // 6. Cart: add items, stepper persistence
    let menuHtml = await (await page.request.get(BASE + '?page=menu')).text();
    const menuCsrf = (menuHtml.match(/name="csrf" value="([a-f0-9]{64})"/) || [])[1];
    res = await post(page, { csrf: menuCsrf, action: 'cart-add', id: '1' });
    ok('add item 1 (North Hall)', res.status() === 303);
    res = await post(page, { csrf: menuCsrf, action: 'cart-add', id: '3' });
    ok('add item 3 (North Hall)', res.status() === 303);
    // cross-kitchen switch prompt
    res = await post(page, { csrf: menuCsrf, action: 'cart-add', id: '4' });
    ok('cross-kitchen add → cart-switch page', res.status() === 303 && res.headers().location.includes('page=cart-switch'));
    // refuse switch, cart unchanged
    let cartHtml = await (await page.request.get(BASE + '?page=cart')).text();
    ok('cart keeps North Hall items', cartHtml.includes('Korean Chicken Bowl') && cartHtml.includes('Pan-fried Gyoza'));
    // now switch
    const switchHtml = await (await page.request.get(BASE + '?page=cart-switch')).text();
    const switchCsrf = (switchHtml.match(/name="csrf" value="([a-f0-9]{64})"/) || [])[1];
    res = await post(page, { csrf: switchCsrf, action: 'cart-switch', id: '4' });
    ok('cart switch accepted', res.status() === 303 && res.headers().location.includes('page=cart'));
    cartHtml = await (await page.request.get(BASE + '?page=cart')).text();
    ok('cart now contains Commons item only', cartHtml.includes('Chicken Caesar Wrap') && !cartHtml.includes('Korean Chicken Bowl'));

    // stepper: bump quantity twice via server (same as UI +)
    const cartCsrf = (cartHtml.match(/name="csrf" value="([a-f0-9]{64})"/) || [])[1];
    res = await post(page, { csrf: cartCsrf, action: 'cart-update', id: '4', quantity: '2' });
    ok('cart-update to 2', res.status() === 303);
    res = await post(page, { csrf: cartCsrf, action: 'cart-update', id: '4', quantity: '99' });
    ok('quantity 99 accepted', res.status() === 303);
    res = await post(page, { csrf: cartCsrf, action: 'cart-update', id: '4', quantity: '100' });
    ok('quantity 100 rejected', res.status() === 303);
    res = await post(page, { csrf: cartCsrf, action: 'cart-update', id: '4', quantity: '2' });
    ok('quantity back to 2', res.status() === 303);

    // 7. Checkout pickup
    let checkoutHtml = await (await page.request.get(BASE + '?page=checkout')).text();
    const checkoutCsrf = (checkoutHtml.match(/name="csrf" value="([a-f0-9]{64})"/) || [])[1];
    const key = (checkoutHtml.match(/name="submission_key" value="([a-f0-9]{64})"/) || [])[1];
    ok('checkout has submission key', !!key);
    res = await post(page, { csrf: checkoutCsrf, action: 'place-order', submission_key: key, fulfillment: 'pickup' });
    ok('pickup order placed', res.status() === 303 && res.headers().location.includes('page=order'));
    const orderId = (res.headers().location.match(/id=(\d+)/) || [])[1];
    // idempotent resubmit returns same order
    res = await post(page, { csrf: checkoutCsrf, action: 'place-order', submission_key: key, fulfillment: 'pickup' });
    ok('double-submit returns same order id', res.status() === 303 && res.headers().location.includes('id=' + orderId));
    let orderHtml = await (await page.request.get(BASE + '?page=order&id=' + orderId)).text();
    ok('order page shows pending', orderHtml.includes('status-badge status-pending'));
    ok('order page shows self-pickup', orderHtml.includes('Self-pickup'));

    // 8. Second order with delivery
    res = await post(page, { csrf: menuCsrf, action: 'cart-add', id: '6' });
    ok('add drink to cart', res.status() === 303);
    checkoutHtml = await (await page.request.get(BASE + '?page=checkout')).text();
    const csrf2 = (checkoutHtml.match(/name="csrf" value="([a-f0-9]{64})"/) || [])[1];
    const key2 = (checkoutHtml.match(/name="submission_key" value="([a-f0-9]{64})"/) || [])[1];
    res = await post(page, { csrf: csrf2, action: 'place-order', submission_key: key2, fulfillment: 'delivery', delivery_location: 'Library, Level 1' });
    ok('delivery order placed', res.status() === 303);
    res = await post(page, { csrf: csrf2, action: 'place-order', submission_key: key2, fulfillment: 'delivery' });
    ok('delivery without location rejected', res.status() === 303);
    const orderId2 = (res.headers().location.match(/id=(\d+)/) || [])[1];
    // stale key reuse after cart emptied → rejected
    res = await post(page, { csrf: csrf2, action: 'place-order', submission_key: key2, fulfillment: 'delivery', delivery_location: 'Library' });
    ok('reused stale key rejected', res.status() === 303);

    // 9. Student cannot touch vendor pages
    res = await page.request.get(BASE + '?page=vendor-orders', { maxRedirects: 0 });
    ok('student blocked from vendor pages (403)', res.status() === 403, String(res.status()));

    // 10. Vendor flow — the pickup order above was placed at The Commons Cafe
    res = await signOut(page);
    res = await login(page, 'commons', 'CampusDemo!2026');
    ok('vendor login ok', res.status() === 303 && res.headers().location.includes('page=vendor-orders'));
    const vendorOrders = await (await page.request.get(BASE + '?page=vendor-orders')).text();
    ok('vendor sees new order in list', vendorOrders.includes('CB-' + String(orderId).padStart(5, '0')));
    // advance: pending → preparing → ready_for_pickup, with real state assertions
    const voCsrf = (vendorOrders.match(/name="csrf" value="([a-f0-9]{64})"/) || [])[1];
    res = await post(page, { csrf: voCsrf, action: 'advance-order', id: orderId, status: 'preparing' });
    ok('advance → preparing', res.status() === 303);
    orderHtml = await (await page.request.get(BASE + '?page=order&id=' + orderId)).text();
    ok('order now Preparing', orderHtml.includes('status-badge status-preparing'));
    res = await post(page, { csrf: voCsrf, action: 'advance-order', id: orderId, status: 'ready_for_pickup' });
    ok('advance → ready_for_pickup', res.status() === 303);
    orderHtml = await (await page.request.get(BASE + '?page=order&id=' + orderId)).text();
    ok('order now Ready for Pickup', orderHtml.includes('status-badge status-ready_for_pickup'));
    res = await post(page, { csrf: voCsrf, action: 'advance-order', id: orderId, status: 'out_for_delivery' });
    ok('invalid step for pickup rejected', res.status() === 303);
    orderHtml = await (await page.request.get(BASE + '?page=order&id=' + orderId)).text();
    ok('invalid step shows error notice', orderHtml.includes('unavailable'));
    res = await post(page, { csrf: voCsrf, action: 'advance-order', id: orderId, status: 'completed' });
    ok('skip step rejected', res.status() === 303);
    orderHtml = await (await page.request.get(BASE + '?page=order&id=' + orderId)).text();
    ok('order history shows Preparing event', /history-list[\s\S]*?<strong>Preparing<\/strong>/.test(orderHtml) && /history-list[\s\S]*?<strong>Ready for Pickup<\/strong>/.test(orderHtml));

    // vendor menu management
    res = await post(page, { csrf: voCsrf, action: 'save-category', name: 'Specials' });
    ok('category created', res.status() === 303);
    res = await post(page, { csrf: voCsrf, action: 'save-item', name: 'Teriyaki Bento', description: 'Grilled chicken bento with rice and greens.', price: '12.50', category_id: '5', is_active: '1' });
    ok('menu item created', res.status() === 303);
    let vendorMenu = await (await page.request.get(BASE + '?page=vendor-menu')).text();
    const itemId = (vendorMenu.match(/action="index\.php"[\s\S]*?name="id" value="(\d+)"[\s\S]*?Teriyaki Bento/) || [])[1];
    const itemCsrf = (vendorMenu.match(/name="csrf" value="([a-f0-9]{64})"/) || [])[1];
    ok('new item visible to vendor', !!itemId);
    res = await post(page, { csrf: itemCsrf, action: 'item-status', id: itemId, operation: 'hide' });
    ok('item hidden', res.status() === 303);
    res = await post(page, { csrf: itemCsrf, action: 'item-status', id: itemId, operation: 'publish' });
    ok('item republished', res.status() === 303);
    // student menu shows the new item (fresh student context)
    res = await signOut(page);
    res = await login(page, 'student2', 'CampusDemo!2026');
    const studentMenu = await (await page.request.get(BASE + '?page=menu')).text();
    ok('new item visible on student menu', studentMenu.includes('Teriyaki Bento'));
    res = await signOut(page);

    // 11. Admin flow
    res = await login(page, 'campusadmin', 'CampusDemo!2026');
    ok('admin login ok', res.status() === 303 && res.headers().location.includes('page=admin-students'));
    const adminHtml = await (await page.request.get(BASE + '?page=admin-students')).text();
    const adminCsrf = (adminHtml.match(/name="csrf" value="([a-f0-9]{64})"/) || [])[1];
    ok('admin sees student1 + validuser', adminHtml.includes('student1') && adminHtml.includes('validuser'));
    res = await post(page, { csrf: adminCsrf, action: 'student-status', id: '1', is_active: '0' });
    ok('student1 disabled', res.status() === 303);
    // disabled student session must die
    const studentCtx = await browser.newContext();
    const sp = await studentCtx.newPage();
    await login(sp, 'student1', 'CampusDemo!2026');
    const afterDisable = await (await sp.request.get(BASE + '?page=menu')).text();
    ok('disabled student cannot order', afterDisable.includes('disabled'), '');
    res = await post(page, { csrf: adminCsrf, action: 'student-status', id: '1', is_active: '1' });
    ok('student1 re-enabled', res.status() === 303);
    // admin kitchen + vendor account
    res = await post(page, { csrf: adminCsrf, action: 'save-kitchen', name: 'Test Kitchen', pickup_location: 'Block C, Level 2', is_active: '1' });
    ok('kitchen created', res.status() === 303);
    res = await post(page, { csrf: adminCsrf, action: 'save-vendor-account', username: 'testvendor', email: 'testvendor@campusbite.test', password: 'Vendor1!abc', confirm_password: 'Vendor1!abc', vendor_id: '3' });
    ok('vendor account created', res.status() === 303);
    res = await signOut(page);
    res = await login(page, 'testvendor', 'Vendor1!abc');
    ok('new vendor account can sign in', res.status() === 303 && res.headers().location.includes('page=vendor-orders'));

    // 12. Admin cannot access vendor pages
    res = await signOut(page);
    await login(page, 'campusadmin', 'CampusDemo!2026');
    res = await page.request.get(BASE + '?page=vendor-menu', { maxRedirects: 0 });
    ok('admin blocked from vendor pages (403)', res.status() === 403, String(res.status()));

    await browser.close();
    const failed = results.filter((r) => !r.pass);
    for (const r of results) console.log(`${r.pass ? 'PASS' : 'FAIL'}  ${r.name}${r.detail ? '  → ' + r.detail : ''}`);
    console.log(`\n${results.length - failed.length}/${results.length} passed`);
    process.exit(failed.length ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(1); });

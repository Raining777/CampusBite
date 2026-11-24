<?php

declare(strict_types=1);

function missing(?array $user): never
{
    http_response_code(404);
    render('message', 'Page not found', $user, ['message' => 'We couldn’t find that page for your account.']);
    exit;
}

function selected(string|int $current, string|int $option): string
{
    return (string) $current === (string) $option ? ' selected' : '';
}

function checked(bool $state): string
{
    return $state ? ' checked' : '';
}

function form_fields(string $action, ?int $id = null): void
{
    ?>
    <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
    <input type="hidden" name="action" value="<?= e($action) ?>">
    <?php if ($id): ?><input type="hidden" name="id" value="<?= e($id) ?>"><?php endif; ?>
    <?php
}

function field(string $name, string $label, string $value = '', string $type = 'text', int $maximum = 0, string $autocomplete = '', bool $required = true): void
{
    ?>
    <label for="<?= e($name) ?>" class="form-label"><?= e($label) ?></label>
    <input class="form-control" id="<?= e($name) ?>" name="<?= e($name) ?>" type="<?= e($type) ?>"
        value="<?= e($value) ?>"<?= $maximum ? ' maxlength="' . $maximum . '"' : '' ?>
        <?= $autocomplete ? ' autocomplete="' . e($autocomplete) . '"' : '' ?><?= $required ? ' required' : '' ?>>
    <?php
}

function page_heading(string $title, string $description = '', ?string $action = null, ?string $actionLabel = null): void
{
    ?>
    <header class="page-heading">
        <div><h1><?= e($title) ?></h1><?php if ($description): ?><p><?= e($description) ?></p><?php endif; ?></div>
        <?php if ($action): ?><a class="button button-primary" href="<?= e(url($action)) ?>"><?= e($actionLabel) ?></a><?php endif; ?>
    </header>
    <?php
}

const EMPTY_ICONS = [
    'bowl' => '<path d="M4 11h16v2a8 8 0 0 1-8 8 8 8 0 0 1-8-8z"/><path d="M6 5c0 2.4-1 4-1 5.6M12 5c0 2.4-1 4-1 5.6M18 5c0 2.4-1 4-1 5.6"/>',
    'bag' => '<path d="M5 8h14l-1 12a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/>',
    'orders' => '<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 9h6M9 13h6M9 17h3"/>',
    'dish' => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="4.5"/>',
    'tag' => '<path d="M3 12V4h8l10 10-8 8z"/><circle cx="8" cy="8" r="1.4"/>',
    'store' => '<path d="M4 10l1.5-6h13L20 10M4 10v10h16V10M9 20v-6h6v6"/>',
    'users' => '<circle cx="9" cy="8" r="3.4"/><path d="M3 20c.6-3.6 3-5.4 6-5.4s5.4 1.8 6 5.4"/><circle cx="17" cy="9" r="2.6"/><path d="M16.4 14.8c2.6.3 4.2 1.9 4.6 4.4"/>',
];

function empty_state(string $title, string $description, ?string $link = null, ?string $label = null, string $icon = 'bowl'): void
{
    $iconPath = EMPTY_ICONS[$icon] ?? EMPTY_ICONS['bowl'];
    ?>
    <section class="empty-state">
        <div class="empty-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><?= $iconPath ?></svg></div>
        <h2><?= e($title) ?></h2><p><?= e($description) ?></p>
        <?php if ($link): ?><a class="button button-outline" href="<?= e($link) ?>"><?= e($label) ?></a><?php endif; ?>
    </section>
    <?php
}

function status_badge(string $status): void
{
    ?><span class="status-badge status-<?= e(str_replace(' ', '-', $status)) ?>"><?= e(ORDER_STATUSES[$status] ?? ucfirst($status)) ?></span><?php
}

function order_number(int $id): string
{
    return 'CB-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT);
}

function order_method(string $fulfillment): string
{
    return $fulfillment === 'pickup' ? 'Self-pickup' : 'Campus delivery';
}

function render(string $page, string $title, ?array $user, array $data = []): void
{
    $role = $user['role'] ?? '';
    $home = $user ? role_home($role) : 'menu';
    $navigation = match ($role) {
        'student' => ['menu' => 'Menu', 'orders' => 'My orders'],
        'vendor' => ['vendor-orders' => 'Orders', 'vendor-menu' => 'Menu items', 'vendor-categories' => 'Categories'],
        'admin' => ['admin-students' => 'Students', 'admin-kitchens' => 'Kitchens', 'admin-vendor-accounts' => 'Vendor accounts'],
        default => ['menu' => 'Menu'],
    };
    $current = match ($page) {
        'order' => $role === 'vendor' ? 'vendor-orders' : 'orders',
        'vendor-item' => 'vendor-menu', 'vendor-category' => 'vendor-categories',
        'admin-kitchen' => 'admin-kitchens', 'admin-vendor-account' => 'admin-vendor-accounts',
        default => $page,
    };
    $cartCount = $role === 'student' && $page !== 'message' ? cart_quantity((int) $user['id']) : 0;
    $vendorPending = $role === 'vendor' ? (int) (row('SELECT COUNT(*) AS total FROM orders WHERE vendor_id = ? AND status = "pending"', [$user['vendor_id']])['total'] ?? 0) : 0;
    $notice = take_session('notice');
    $old = take_session('old');
    $values = $data['record'] ?? [];
    if ($old && $old['page'] === $page && ($data['id'] ?? 0) === $old['id']) {
        $values = array_replace($values, $old['fields']);
    }
    $input = static fn (string $name, string|int $default = '') => is_scalar($values[$name] ?? null) ? (string) $values[$name] : (string) $default;
    ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#fffdf7">
    <meta name="description" content="Browse campus kitchens and order food for pickup or campus delivery.">
    <meta property="og:title" content="CampusBite">
    <meta property="og:description" content="Browse campus kitchens and order food for pickup or campus delivery.">
    <meta property="og:type" content="website">
    <title><?= e($title) ?> | CampusBite</title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Crect width='64' height='64' rx='15' fill='%2314402e'/%3E%3Cpath d='M13 37h38v4a12 12 0 0 1-12 12H25a12 12 0 0 1-12-12z' fill='%23dff09f'/%3E%3Cpath d='M22 13c0 6.5-3 10.5-3 15M32 13c0 6.5-3 10.5-3 15M42 13c0 6.5-3 10.5-3 15' stroke='%23dff09f' stroke-width='4' fill='none' stroke-linecap='round'/%3E%3C/svg%3E">
    <link rel="stylesheet" href="assets/bootstrap.min.css">
    <link rel="stylesheet" href="assets/app.css">
    <script src="assets/app.js" defer></script>
</head>
<body id="top">
<a class="skip-link" href="#main">Skip to content</a>
<header class="site-header" data-header>
    <div class="site-width header-inner">
        <a class="brand" href="<?= e(url($home)) ?>" aria-label="CampusBite home"><span class="brand-icon" aria-hidden="true"></span><span>Campus<span class="brand-accent">Bite</span></span></a>
        <nav class="site-nav" aria-label="Main navigation">
            <?php foreach ($navigation as $route => $label): ?>
                <a href="<?= e(url($route)) ?>"<?= $current === $route ? ' aria-current="page"' : '' ?>><?= e($label) ?><?php if ($route === 'vendor-orders' && $vendorPending): ?><span class="nav-badge"><?= e($vendorPending) ?></span><?php endif; ?></a>
            <?php endforeach; ?>
        </nav>
        <div class="header-actions">
            <?php if ($role === 'student'): ?>
                <a class="button button-outline cart-button" href="<?= e(url('cart')) ?>"<?= in_array($page, ['cart', 'cart-switch', 'checkout'], true) ? ' aria-current="page"' : '' ?>><span class="bag-icon" aria-hidden="true"></span> Cart <span class="cart-count"><?= e($cartCount) ?></span></a>
            <?php endif; ?>
            <?php if ($user): ?>
                <form method="post" action="index.php" class="inline-form">
                    <?php form_fields('logout'); ?><button class="button button-quiet" type="submit">Sign out</button>
                </form>
            <?php else: ?>
                <a class="button button-quiet" href="<?= e(url('login')) ?>"<?= $page === 'login' ? ' aria-current="page"' : '' ?>>Sign in</a>
                <a class="button button-primary header-register" href="<?= e(url('register')) ?>">Create account</a>
            <?php endif; ?>
        </div>
    </div>
</header>
<main id="main" class="site-width main-content" tabindex="-1">
    <?php if ($notice): ?>
        <div class="notice notice-<?= e($notice['type']) ?>" role="<?= $notice['type'] === 'error' ? 'alert' : 'status' ?>"><?= e($notice['message']) ?></div>
    <?php endif; ?>
    <?php if (str_starts_with($page, 'vendor-') || str_starts_with($page, 'admin-')): ?>
        <?php require __DIR__ . '/workspace-view.php'; ?>
    <?php else: ?>
        <?php require __DIR__ . '/student-view.php'; ?>
    <?php endif; ?>
</main>
<footer class="site-footer">
    <div class="site-width footer-inner">
        <div class="footer-brand"><a href="<?= e(url($home)) ?>">Campus<em>Bite</em></a><span>Campus food ordering · Pickup and delivery between classes</span></div>
        <div class="footer-links"><a href="<?= e(url('menu')) ?>">Menu</a><a href="#top">Back to top</a></div>
    </div>
</footer>
</body>
</html>
    <?php
}

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

function empty_state(string $title, string $description, ?string $link = null, ?string $label = null): void
{
    ?>
    <section class="empty-state">
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
    <meta name="theme-color" content="#17392c">
    <meta name="description" content="Browse campus kitchens and order food for pickup or campus delivery.">
    <title><?= e($title) ?> | CampusBite</title>
    <link rel="stylesheet" href="assets/bootstrap.min.css">
    <link rel="stylesheet" href="assets/app.css">
    <script src="assets/app.js" defer></script>
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>
<header class="site-header">
    <div class="site-width header-inner">
        <a class="brand" href="<?= e(url($home)) ?>" aria-label="CampusBite home"><span class="brand-icon" aria-hidden="true"></span><span>Campus<span class="brand-accent">Bite</span></span></a>
        <nav class="site-nav" aria-label="Main navigation">
            <?php foreach ($navigation as $route => $label): ?>
                <a href="<?= e(url($route)) ?>"<?= $current === $route ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
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
<footer class="site-footer"><div class="site-width footer-inner"><a href="<?= e(url($home)) ?>">CampusBite</a><span>Campus food ordering</span></div></footer>
</body>
</html>
    <?php
}

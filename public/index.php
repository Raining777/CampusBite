<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/accounts.php';
require dirname(__DIR__) . '/app/ordering.php';
require dirname(__DIR__) . '/app/management.php';
require dirname(__DIR__) . '/app/views.php';
require dirname(__DIR__) . '/app/actions.php';

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
start_session();
$page = value($_GET, 'page') ?: 'menu';
$id = natural($_GET['id'] ?? null);
$user = null;

try {
    $user = signed_in_user();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        handle_post($user);
    }
    if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD'], true)) {
        header('Allow: GET, HEAD, POST');
        http_response_code(405);
        render('message', 'Method unavailable', $user, ['message' => 'Return to the menu and try again.']);
        exit;
    }
    $routes = [
        'menu' => ['any', 'Campus menu'], 'login' => ['guest', 'Sign in'], 'register' => ['guest', 'Create an account'],
        'cart' => ['student', 'Your cart'], 'cart-switch' => ['student', 'Switch kitchens'], 'checkout' => ['student', 'Checkout'],
        'orders' => ['student', 'My orders'], 'order' => ['signed-in', 'Order details'],
        'vendor-orders' => ['vendor', 'Kitchen orders'], 'vendor-menu' => ['vendor', 'Kitchen menu'],
        'vendor-item' => ['vendor', $id ? 'Edit menu item' : 'Add menu item'],
        'vendor-categories' => ['vendor', 'Categories'], 'vendor-category' => ['vendor', $id ? 'Edit category' : 'Add category'],
        'admin-students' => ['admin', 'Student accounts'], 'admin-kitchens' => ['admin', 'Kitchens'],
        'admin-kitchen' => ['admin', $id ? 'Edit kitchen' : 'Add kitchen'],
        'admin-vendor-accounts' => ['admin', 'Vendor accounts'],
        'admin-vendor-account' => ['admin', $id ? 'Edit vendor account' : 'Add vendor account'],
    ];
    if (!isset($routes[$page])) {
        missing($user);
    }
    [$role, $title] = $routes[$page];
    require_access($role, $user);
    $data = [];
    switch ($page) {
        case 'menu':
            $data['filters'] = menu_filters($_GET);
            $data['items'] = menu_items($data['filters']);
            $data['vendors'] = rows('SELECT id, name FROM vendors WHERE is_active = 1 ORDER BY name');
            $data['categories'] = menu_categories($data['filters']['vendor']);
            break;
        case 'cart':
        case 'checkout':
            $data['items'] = cart_items((int) $user['id']);
            if ($page === 'checkout') {
                try {
                    check_cart($data['items']);
                } catch (RuleViolation $error) {
                    notice('error', $error->getMessage());
                    redirect('cart');
                }
                $data['key'] = checkout_key($data['items']);
            }
            break;
        case 'cart-switch':
            $data['switch'] = $_SESSION['cart_switch'] ?? null;
            if (!$data['switch']) {
                redirect('cart');
            }
            break;
        case 'orders':
            $data['orders'] = student_orders((int) $user['id']);
            break;
        case 'order':
            if ($user['role'] === 'admin' || !($data['order'] = transaction(fn () => own_order($user, $id)))) {
                missing($user);
            }
            $title = 'Order ' . order_number($id);
            break;
        case 'vendor-orders':
            $selection = value($_GET, 'status');
            $data['status'] = isset(ORDER_STATUSES[$selection]) ? $selection : '';
            $data['orders'] = vendor_orders((int) $user['vendor_id'], $data['status']);
            break;
        case 'vendor-menu':
            $data['items'] = kitchen_items((int) $user['vendor_id']);
            break;
        case 'vendor-categories':
            $data['categories'] = kitchen_categories((int) $user['vendor_id']);
            break;
        case 'vendor-item':
            $data['categories'] = kitchen_categories((int) $user['vendor_id']);
            $data['record'] = $id ? kitchen_item((int) $user['vendor_id'], $id) : [];
            break;
        case 'vendor-category':
            $data['record'] = $id ? kitchen_category((int) $user['vendor_id'], $id) : [];
            break;
        case 'admin-students':
            $data['q'] = trim(value($_GET, 'q'));
            $selection = value($_GET, 'status');
            $data['status'] = in_array($selection, ['active', 'disabled'], true) ? $selection : '';
            $data['students'] = admin_students($data['q'], $data['status']);
            break;
        case 'admin-kitchens':
            $data['vendors'] = rows("SELECT v.*, (SELECT COUNT(*) FROM users u WHERE u.vendor_id = v.id AND u.role = 'vendor') AS account_count FROM vendors v ORDER BY v.name");
            break;
        case 'admin-kitchen':
            $data['record'] = $id ? row('SELECT * FROM vendors WHERE id = ?', [$id]) : [];
            break;
        case 'admin-vendor-accounts':
            $data['accounts'] = rows("SELECT u.id, u.username, u.email, u.is_active, v.name AS vendor_name, v.is_active AS vendor_active
                FROM users u JOIN vendors v ON v.id = u.vendor_id WHERE u.role = 'vendor' ORDER BY v.name, u.username");
            break;
        case 'admin-vendor-account':
            $data['vendors'] = rows('SELECT id, name, is_active FROM vendors ORDER BY name');
            $data['record'] = $id ? row("SELECT id, username, email, vendor_id FROM users WHERE id = ? AND role = 'vendor'", [$id]) : [];
            break;
    }
    if (array_key_exists('record', $data) && $data['record'] === null) {
        missing($user);
    }
    render($page, $title, $user, $data + ['id' => $id]);
} catch (Throwable $error) {
    error_log((string) $error);
    http_response_code(503);
    render('message', 'CampusBite is temporarily unavailable', $user, [
        'message' => 'Try again in a moment. For a new installation, check the database setup in the README.',
    ]);
}

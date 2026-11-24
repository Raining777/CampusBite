<?php

declare(strict_types=1);

const ACTIONS = [
    'login' => ['guest', 'login'], 'register' => ['guest', 'register'], 'logout' => ['signed-in', 'menu'],
    'cart-add' => ['student', 'menu'], 'cart-switch' => ['student', 'cart'],
    'cart-update' => ['student', 'cart'], 'cart-remove' => ['student', 'cart'], 'place-order' => ['student', 'checkout'],
    'save-item' => ['vendor', 'vendor-item'], 'item-status' => ['vendor', 'vendor-menu'],
    'remove-item' => ['vendor', 'vendor-menu'], 'save-category' => ['vendor', 'vendor-category'],
    'remove-category' => ['vendor', 'vendor-categories'], 'advance-order' => ['vendor', 'order'],
    'student-status' => ['admin', 'admin-students'], 'save-kitchen' => ['admin', 'admin-kitchen'],
    'save-vendor-account' => ['admin', 'admin-vendor-account'], 'vendor-account-status' => ['admin', 'admin-vendor-accounts'],
];

function require_access(string $role, ?array $user): void
{
    if ($role === 'guest' && $user) {
        redirect(role_home($user['role']));
    }
    if (in_array($role, ['any', 'guest'], true)) {
        return;
    }
    if (!$user) {
        if (!isset($_SESSION['notice'])) {
            notice('info', 'Sign in to continue.');
        }
        redirect('login');
    }
    if ($role !== 'signed-in' && $user['role'] !== $role) {
        http_response_code(403);
        render('message', 'Access restricted', $user, ['message' => 'This page is unavailable to your account.']);
        exit;
    }
}

function menu_parameters(array $input): array
{
    $filters = menu_filters($input);
    return array_filter($filters, static fn ($value) => $value !== '' && $value !== 0);
}

function complete(string $page, string $message, array $parameters = []): never
{
    notice('success', $message);
    redirect($page, $parameters);
}

function handle_post(?array $user): never
{
    $action = value($_POST, 'action');
    if (!isset(ACTIONS[$action])) {
        http_response_code(400);
        render('message', 'Page unavailable', $user, ['message' => 'Return to the menu and try again.']);
        exit;
    }
    [$role, $destination] = ACTIONS[$action];
    require_access($role, $user);
    $id = natural($_POST['id'] ?? null);
    $return = match ($destination) {
        'vendor-item', 'vendor-category', 'order', 'admin-kitchen', 'admin-vendor-account' => $id ? ['id' => $id] : [],
        'menu' => menu_parameters($_POST),
        default => [],
    };
    try {
        if (!hash_equals($_SESSION['csrf'], value($_POST, 'csrf'))) {
            throw new RuleViolation('This form expired. Reload the page and try again.');
        }
        switch ($action) {
            case 'login':
                $account = sign_in($_POST);
                redirect(role_home($account['role']));
            case 'register':
                save_account($_POST, 'student');
                complete('login', 'Your student account is ready. Sign in to start ordering.');
            case 'logout':
                clear_sign_in();
                redirect('menu');
            case 'cart-add':
                $switch = add_cart_item($user, $id);
                if ($switch) {
                    $_SESSION['cart_switch'] = $switch;
                    redirect('cart-switch');
                }
                unset($_SESSION['cart_switch']);
                complete('menu', 'Added to your cart.', $return);
            case 'cart-switch':
                $switch = take_session('cart_switch');
                if (!$switch || (int) $switch['item_id'] !== $id) {
                    throw new RuleViolation('This cart change has expired. Add the item again to switch kitchens.');
                }
                add_cart_item($user, $id, $switch['cart_vendor_id']);
                complete('cart', 'Your cart now contains items from ' . $switch['vendor_name'] . '.');
            case 'cart-update':
                update_cart_item($user, $id, natural($_POST['quantity'] ?? null));
                complete('cart', 'Cart updated.');
            case 'cart-remove':
                update_cart_item($user, $id, null);
                complete('cart', 'Item removed from your cart.');
            case 'place-order':
                $orderId = place_order($user, $_POST);
                if (($_SESSION['checkout']['key'] ?? '') === value($_POST, 'submission_key')) {
                    unset($_SESSION['checkout']);
                }
                complete('order', 'Order received. Your kitchen will start preparing it shortly.', ['id' => $orderId]);
            case 'save-item':
                save_item($user, $id, $_POST);
                complete('vendor-menu', 'Menu item saved.');
            case 'item-status':
                $operation = value($_POST, 'operation');
                if (!in_array($operation, ['publish', 'hide'], true)) {
                    throw new RuleViolation('Choose whether to publish or hide the item.');
                }
                set_item_availability($user, $id, $operation);
                complete('vendor-menu', $operation === 'publish' ? 'Item published.' : 'Item hidden from the student menu.');
            case 'remove-item':
                set_item_availability($user, $id, 'remove');
                complete('vendor-menu', 'Item removed from your menu.');
            case 'save-category':
                save_category($user, $id, $_POST);
                complete('vendor-categories', 'Category saved.');
            case 'remove-category':
                remove_category($user, $id);
                complete('vendor-categories', 'Category removed.');
            case 'advance-order':
                advance_order($user, $id, value($_POST, 'status'));
                complete('order', 'Order status updated.', ['id' => $id]);
            case 'student-status':
            case 'vendor-account-status':
                $active = value($_POST, 'is_active') === '1';
                $accountRole = $action === 'student-status' ? 'student' : 'vendor';
                set_account_status($user, $accountRole, $id, $active);
                complete($destination, $active ? 'Account enabled.' : 'Account disabled.');
            case 'save-kitchen':
                save_kitchen($user, $id, $_POST);
                complete('admin-kitchens', 'Kitchen saved.');
            case 'save-vendor-account':
                save_vendor_account($user, $id, $_POST);
                complete('admin-vendor-accounts', 'Vendor account saved.');
        }
    } catch (RuleViolation $error) {
        notice('error', $error->getMessage());
    } catch (PDOException $error) {
        if (!in_array((int) ($error->errorInfo[1] ?? 0), [1205, 1213], true)) {
            throw $error;
        }
        notice('error', 'Another update was made at the same time. Review the page and try again.');
    }
    $_SESSION['old'] = [
        'page' => $destination, 'id' => $id,
        'fields' => array_intersect_key($_POST, array_flip([
            'identifier', 'username', 'email', 'fulfillment', 'delivery_location', 'name', 'description',
            'price', 'category_id', 'is_active', 'pickup_location', 'vendor_id',
        ])),
    ];
    if (in_array($action, ['save-item', 'save-kitchen'], true)) {
        $_SESSION['old']['fields']['is_active'] = value($_POST, 'is_active') === '1' ? '1' : '0';
    }
    redirect($destination, $return);
}

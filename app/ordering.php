<?php

declare(strict_types=1);

function menu_filters(array $input): array
{
    $search = trim(value($input, 'q'));
    $category = trim(value($input, 'category'));
    return [
        'q' => length($search) <= 100 && preg_match('//u', $search) ? $search : '',
        'vendor' => natural($input['vendor'] ?? null),
        'category' => length($category) <= 60 && preg_match('//u', $category) ? $category : '',
    ];
}

function menu_items(array $filters): array
{
    $where = ['m.is_active = 1', 'm.archived_at IS NULL', 'v.is_active = 1', 'c.archived_at IS NULL'];
    $parameters = [];
    if ($filters['q'] !== '') {
        $term = '%' . strtr($filters['q'], ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
        $where[] = "(m.name LIKE ? ESCAPE '!' OR m.description LIKE ? ESCAPE '!' OR v.name LIKE ? ESCAPE '!')";
        array_push($parameters, $term, $term, $term);
    }
    if ($filters['vendor']) {
        $where[] = 'v.id = ?';
        $parameters[] = $filters['vendor'];
    }
    if ($filters['category'] !== '') {
        $where[] = 'c.name = ?';
        $parameters[] = $filters['category'];
    }
    return rows('SELECT m.*, v.name AS vendor_name, c.name AS category_name FROM menu_items m
        JOIN vendors v ON v.id = m.vendor_id JOIN categories c ON c.id = m.category_id
        WHERE ' . implode(' AND ', $where) . ' ORDER BY m.id', $parameters);
}

function menu_categories(int $vendorId): array
{
    return rows('SELECT DISTINCT c.name FROM categories c JOIN vendors v ON v.id = c.vendor_id
        JOIN menu_items m ON m.category_id = c.id AND m.is_active = 1 AND m.archived_at IS NULL
        WHERE c.archived_at IS NULL AND v.is_active = 1 AND (? = 0 OR c.vendor_id = ?) ORDER BY c.name', [$vendorId, $vendorId]);
}

function cart_items(int $studentId, bool $lock = false): array
{
    return rows('SELECT ci.quantity, m.id, m.name, m.price, m.photo_slot, m.vendor_id, v.name AS vendor_name,
        v.pickup_location, (m.is_active AND m.archived_at IS NULL AND v.is_active AND c.archived_at IS NULL) AS available
        FROM cart_items ci JOIN menu_items m ON m.id = ci.menu_item_id
        JOIN vendors v ON v.id = m.vendor_id JOIN categories c ON c.id = m.category_id
        WHERE ci.user_id = ? ORDER BY m.id' . ($lock ? ' FOR UPDATE' : ''), [$studentId]);
}

function cart_total(array $items): int
{
    $total = 0;
    foreach ($items as $item) {
        $total += cents($item['price']) * (int) $item['quantity'];
    }
    return $total;
}

function cart_quantity(int $studentId): int
{
    return (int) row('SELECT COALESCE(SUM(quantity), 0) AS total FROM cart_items WHERE user_id = ?', [$studentId])['total'];
}

function add_cart_item(array $actor, int $itemId, ?int $switchFrom = null): ?array
{
    return transaction(function () use ($actor, $itemId, $switchFrom) {
        $student = lock_actor($actor, 'student');
        $items = cart_items((int) $student['id'], true);
        $item = row('SELECT m.id, m.vendor_id, m.name, v.name AS vendor_name FROM menu_items m
            JOIN vendors v ON v.id = m.vendor_id JOIN categories c ON c.id = m.category_id
            WHERE m.id = ? AND m.is_active = 1 AND m.archived_at IS NULL AND v.is_active = 1 AND c.archived_at IS NULL FOR UPDATE', [$itemId]);
        if (!$item) {
            throw new RuleViolation('This item is no longer available.');
        }
        $current = $items[0] ?? null;
        if ($current && (int) $current['vendor_id'] !== (int) $item['vendor_id']) {
            if ($switchFrom === null) {
                return ['item_id' => $itemId, 'item_name' => $item['name'], 'vendor_name' => $item['vendor_name'],
                    'cart_vendor_id' => (int) $current['vendor_id'], 'cart_vendor' => $current['vendor_name']];
            }
            if ($switchFrom !== (int) $current['vendor_id']) {
                throw new RuleViolation('Your cart has changed. Check your cart before switching kitchens.');
            }
            query('DELETE FROM cart_items WHERE user_id = ?', [$student['id']]);
            $items = [];
        }
        foreach ($items as $line) {
            if ((int) $line['id'] === $itemId) {
                $quantity = (int) $line['quantity'] + 1;
                if ($quantity > 99) {
                    throw new RuleViolation('You can order up to 99 of this item.');
                }
                query('UPDATE cart_items SET quantity = ? WHERE user_id = ? AND menu_item_id = ?', [$quantity, $student['id'], $itemId]);
                return null;
            }
        }
        if (count($items) >= 40) {
            throw new RuleViolation('Your cart can contain up to 40 different items.');
        }
        query('INSERT INTO cart_items (user_id, menu_item_id, quantity) VALUES (?, ?, 1)', [$student['id'], $itemId]);
        return null;
    });
}

function update_cart_item(array $actor, int $itemId, ?int $quantity): void
{
    transaction(function () use ($actor, $itemId, $quantity) {
        $student = lock_actor($actor, 'student');
        if ($quantity === null) {
            query('DELETE FROM cart_items WHERE user_id = ? AND menu_item_id = ?', [$student['id'], $itemId]);
            return;
        }
        if ($quantity < 1 || $quantity > 99) {
            throw new RuleViolation('Quantity must be between 1 and 99.');
        }
        foreach (cart_items((int) $student['id'], true) as $item) {
            if ((int) $item['id'] === $itemId && $item['available']) {
                query('UPDATE cart_items SET quantity = ? WHERE user_id = ? AND menu_item_id = ?', [$quantity, $student['id'], $itemId]);
                return;
            }
        }
        throw new RuleViolation('This cart item is no longer available.');
    });
}

function check_cart(array $items): void
{
    if (!$items) {
        throw new RuleViolation('Your cart is empty.');
    }
    $vendorId = $items[0]['vendor_id'];
    if (count($items) > 40) {
        throw new RuleViolation('Your cart can contain up to 40 different items.');
    }
    foreach ($items as $item) {
        if (!$item['available'] || $item['vendor_id'] !== $vendorId || $item['quantity'] < 1 || $item['quantity'] > 99) {
            throw new RuleViolation('Review your cart and remove unavailable items before checking out.');
        }
    }
}

function cart_signature(array $items): string
{
    return hash('sha256', json_encode($items, JSON_THROW_ON_ERROR));
}

function checkout_key(array $items): string
{
    $signature = cart_signature($items);
    if (($_SESSION['checkout']['signature'] ?? '') !== $signature) {
        $_SESSION['checkout'] = ['key' => bin2hex(random_bytes(32)), 'signature' => $signature];
    }
    return $_SESSION['checkout']['key'];
}

function place_order(array $actor, array $input): int
{
    return transaction(function () use ($actor, $input) {
        $student = lock_actor($actor, 'student');
        $key = value($input, 'submission_key');
        if (!preg_match('/^[a-f0-9]{64}$/D', $key)) {
            throw new RuleViolation('Reload checkout and try again.');
        }
        $existing = row('SELECT id FROM orders WHERE student_id = ? AND submission_key = ?', [$student['id'], $key]);
        if ($existing) {
            return (int) $existing['id'];
        }
        $items = cart_items((int) $student['id'], true);
        check_cart($items);
        $checkout = $_SESSION['checkout'] ?? [];
        if (!hash_equals($checkout['key'] ?? '', $key) || ($checkout['signature'] ?? '') !== cart_signature($items)) {
            throw new RuleViolation('Your cart or prices changed. Review the current order before placing it.');
        }
        $fulfillment = value($input, 'fulfillment');
        if (!in_array($fulfillment, ['pickup', 'delivery'], true)) {
            throw new RuleViolation('Choose pickup or campus delivery.');
        }
        $location = $fulfillment === 'delivery' ? field_text($input, 'delivery_location', 'Campus delivery location', 150, 3) : null;
        $vendor = $items[0];
        query('INSERT INTO orders (student_id, vendor_id, vendor_name, pickup_location, total, fulfillment, delivery_location, submission_key)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)', [$student['id'], $vendor['vendor_id'], $vendor['vendor_name'], $vendor['pickup_location'], decimal(cart_total($items)), $fulfillment, $location, $key]);
        $orderId = (int) db()->lastInsertId();
        foreach ($items as $item) {
            query('INSERT INTO order_items (order_id, menu_item_id, item_name, unit_price, quantity) VALUES (?, ?, ?, ?, ?)',
                [$orderId, $item['id'], $item['name'], $item['price'], $item['quantity']]);
        }
        query("INSERT INTO order_status_history (order_id, new_status, changed_by) VALUES (?, 'pending', ?)", [$orderId, $student['id']]);
        query('DELETE FROM cart_items WHERE user_id = ?', [$student['id']]);
        return $orderId;
    });
}

const ORDER_STATUSES = [
    'pending' => 'Pending', 'preparing' => 'Preparing', 'ready_for_pickup' => 'Ready for Pickup',
    'out_for_delivery' => 'Out for Delivery', 'completed' => 'Completed',
];

function order_steps(string $fulfillment): array
{
    return ['pending', 'preparing', $fulfillment === 'pickup' ? 'ready_for_pickup' : 'out_for_delivery', 'completed'];
}

function next_status(array $order): ?string
{
    $steps = order_steps($order['fulfillment']);
    $current = array_search($order['status'], $steps, true);
    return $current === false ? null : ($steps[$current + 1] ?? null);
}

function student_orders(int $studentId): array
{
    return rows('SELECT * FROM orders WHERE student_id = ? ORDER BY created_at DESC, id DESC', [$studentId]);
}

function vendor_orders(int $vendorId, string $status): array
{
    return rows("SELECT o.*, u.username AS student_name FROM orders o JOIN users u ON u.id = o.student_id
        WHERE o.vendor_id = ? AND (? = '' OR o.status = ?)
        ORDER BY (o.status = 'completed'), o.created_at DESC, o.id DESC", [$vendorId, $status, $status]);
}

function own_order(array $actor, int $id, bool $lock = false): ?array
{
    $owner = $actor['role'] === 'vendor' ? 'vendor_id' : 'student_id';
    $ownerId = $actor['role'] === 'vendor' ? $actor['vendor_id'] : $actor['id'];
    $order = row("SELECT * FROM orders WHERE id = ? AND $owner = ?" . ($lock ? ' FOR UPDATE' : ''), [$id, $ownerId]);
    if ($order && !$lock) {
        $order['student_name'] = row('SELECT username FROM users WHERE id = ?', [$order['student_id']])['username'];
        $order['items'] = rows('SELECT * FROM order_items WHERE order_id = ? ORDER BY id', [$id]);
        $order['history'] = rows('SELECT * FROM order_status_history WHERE order_id = ? ORDER BY id', [$id]);
    }
    return $order;
}

function advance_order(array $actor, int $orderId, string $status): void
{
    transaction(function () use ($actor, $orderId, $status) {
        $vendor = lock_actor($actor, 'vendor');
        $order = own_order($vendor, $orderId, true);
        if (!$order) {
            throw new RuleViolation('Order not found.');
        }
        if (!in_array($status, order_steps($order['fulfillment']), true)) {
            throw new RuleViolation('That status change is unavailable. Refresh the order and use the next step.');
        }
        query('UPDATE orders SET status = ? WHERE id = ? AND vendor_id = ?', [$status, $orderId, $vendor['vendor_id']]);
        query('INSERT INTO order_status_history (order_id, old_status, new_status, changed_by) VALUES (?, ?, ?, ?)',
            [$orderId, $order['status'], $status, $vendor['id']]);
    });
}

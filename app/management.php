<?php

declare(strict_types=1);

function kitchen_categories(int $vendorId): array
{
    return rows('SELECT c.*, (SELECT COUNT(*) FROM menu_items m WHERE m.category_id = c.id AND m.archived_at IS NULL) AS item_count
        FROM categories c WHERE c.vendor_id = ? AND c.archived_at IS NULL ORDER BY c.name', [$vendorId]);
}

function kitchen_items(int $vendorId): array
{
    return rows('SELECT m.*, c.name AS category_name FROM menu_items m JOIN categories c ON c.id = m.category_id
        WHERE m.vendor_id = ? AND m.archived_at IS NULL ORDER BY m.is_active DESC, m.name', [$vendorId]);
}

function kitchen_item(int $vendorId, int $id): ?array
{
    return row('SELECT * FROM menu_items WHERE id = ? AND vendor_id = ? AND archived_at IS NULL', [$id, $vendorId]);
}

function kitchen_category(int $vendorId, int $id): ?array
{
    return row('SELECT * FROM categories WHERE id = ? AND vendor_id = ? AND archived_at IS NULL', [$id, $vendorId]);
}

function save_category(array $actor, int $id, array $input): void
{
    $name = field_text($input, 'name', 'Category name', 60);
    transaction(function () use ($actor, $id, $name) {
        $vendor = lock_actor($actor, 'vendor');
        $vendorId = (int) $vendor['vendor_id'];
        $sameName = row('SELECT * FROM categories WHERE vendor_id = ? AND name = ?', [$vendorId, $name]);
        if ($sameName && (int) $sameName['id'] !== $id) {
            if ($id || !$sameName['archived_at']) {
                throw new RuleViolation('This kitchen already has a category with that name.');
            }
            query('UPDATE categories SET name = ?, archived_at = NULL WHERE id = ?', [$name, $sameName['id']]);
            return;
        }
        if (!$id) {
            query('INSERT INTO categories (vendor_id, name) VALUES (?, ?)', [$vendorId, $name]);
            return;
        }
        if (!kitchen_category($vendorId, $id)) {
            throw new RuleViolation('Category not found.');
        }
        query('UPDATE categories SET name = ? WHERE id = ? AND vendor_id = ?', [$name, $id, $vendorId]);
    });
}

function remove_category(array $actor, int $id): void
{
    transaction(function () use ($actor, $id) {
        $vendor = lock_actor($actor, 'vendor');
        $vendorId = (int) $vendor['vendor_id'];
        if (!kitchen_category($vendorId, $id)) {
            throw new RuleViolation('Category not found.');
        }
        if (row('SELECT id FROM menu_items WHERE vendor_id = ? AND category_id = ? AND archived_at IS NULL LIMIT 1', [$vendorId, $id])) {
            throw new RuleViolation('Move or remove the menu items in this category first.');
        }
        query('UPDATE categories SET archived_at = CURRENT_TIMESTAMP WHERE id = ? AND vendor_id = ?', [$id, $vendorId]);
    });
}

function save_item(array $actor, int $id, array $input): void
{
    $name = field_text($input, 'name', 'Item name', 100);
    $description = field_text($input, 'description', 'Description', 500);
    $price = price(trim(value($input, 'price')));
    $categoryId = natural($input['category_id'] ?? null);
    $active = value($input, 'is_active') === '1';
    transaction(function () use ($actor, $id, $name, $description, $price, $categoryId, $active) {
        $vendor = lock_actor($actor, 'vendor');
        $vendorId = (int) $vendor['vendor_id'];
        if (!kitchen_category($vendorId, $categoryId)) {
            throw new RuleViolation('Choose a category from your kitchen.');
        }
        if (!$id) {
            query('INSERT INTO menu_items (vendor_id, category_id, name, description, price, is_active) VALUES (?, ?, ?, ?, ?, ?)',
                [$vendorId, $categoryId, $name, $description, $price, (int) $active]);
            return;
        }
        if (!kitchen_item($vendorId, $id)) {
            throw new RuleViolation('Menu item not found.');
        }
        query('UPDATE menu_items SET category_id = ?, name = ?, description = ?, price = ?, is_active = ? WHERE id = ? AND vendor_id = ?',
            [$categoryId, $name, $description, $price, (int) $active, $id, $vendorId]);
    });
}

function set_item_availability(array $actor, int $id, string $operation): void
{
    transaction(function () use ($actor, $id, $operation) {
        $vendor = lock_actor($actor, 'vendor');
        $vendorId = (int) $vendor['vendor_id'];
        if (!kitchen_item($vendorId, $id)) {
            throw new RuleViolation('Menu item not found.');
        }
        if ($operation === 'remove') {
            query('UPDATE menu_items SET is_active = 0, archived_at = CURRENT_TIMESTAMP WHERE id = ? AND vendor_id = ?', [$id, $vendorId]);
        } else {
            query('UPDATE menu_items SET is_active = ? WHERE id = ? AND vendor_id = ?', [(int) ($operation === 'publish'), $id, $vendorId]);
        }
    });
}

function admin_students(string $term, string $status): array
{
    $term = '%' . strtr($term, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
    return rows("SELECT id, username, email, is_active, created_at FROM users WHERE role = 'student'
        AND (username LIKE ? ESCAPE '!' OR email LIKE ? ESCAPE '!') AND (? = '' OR is_active = ?)
        ORDER BY username", [$term, $term, $status, (int) ($status === 'active')]);
}

function set_account_status(array $actor, string $role, int $id, bool $active): void
{
    transaction(function () use ($actor, $role, $id, $active) {
        lock_actor($actor, 'admin');
        if (!row('SELECT id FROM users WHERE id = ? AND role = ? FOR UPDATE', [$id, $role])) {
            throw new RuleViolation('Account not found.');
        }
        query('UPDATE users SET auth_version = auth_version + (is_active <> ?), is_active = ? WHERE id = ? AND role = ?',
            [(int) $active, (int) $active, $id, $role]);
    });
}

function save_kitchen(array $actor, int $id, array $input): void
{
    $name = field_text($input, 'name', 'Kitchen name', 100);
    $location = field_text($input, 'pickup_location', 'Pickup location', 150);
    $active = (int) (value($input, 'is_active') === '1');
    try {
        transaction(function () use ($actor, $id, $name, $location, $active) {
            lock_actor($actor, 'admin');
            if (!$id) {
                query('INSERT INTO vendors (name, pickup_location, is_active) VALUES (?, ?, ?)', [$name, $location, $active]);
                return;
            }
            if (!row('SELECT id FROM vendors WHERE id = ? FOR UPDATE', [$id])) {
                throw new RuleViolation('Kitchen not found.');
            }
            query('UPDATE vendors SET name = ?, pickup_location = ?, is_active = ? WHERE id = ?', [$name, $location, $active, $id]);
        });
    } catch (PDOException $error) {
        if (($error->errorInfo[1] ?? 0) === 1062) {
            throw new RuleViolation('A kitchen already uses that name.');
        }
        throw $error;
    }
}

function save_vendor_account(array $actor, int $id, array $input): void
{
    transaction(function () use ($actor, $id, $input) {
        lock_actor($actor, 'admin');
        if ($id) {
            $record = row("SELECT id, vendor_id FROM users WHERE id = ? AND role = 'vendor' FOR UPDATE", [$id]);
            if (!$record) {
                throw new RuleViolation('Vendor account not found.');
            }
            save_account($input, 'vendor', (int) $record['vendor_id'], $id);
            return;
        }
        $vendorId = natural($input['vendor_id'] ?? null);
        if (!row('SELECT id FROM vendors WHERE id = ? FOR UPDATE', [$vendorId])) {
            throw new RuleViolation('Choose a kitchen for this account.');
        }
        save_account($input, 'vendor', $vendorId);
    });
}

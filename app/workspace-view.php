<?php

declare(strict_types=1);

switch ($page):
    case 'vendor-orders':
        page_heading($title, $user['vendor_name']);
        ?>
        <form class="toolbar filter-bar" method="get" action="index.php">
            <input type="hidden" name="page" value="vendor-orders">
            <label for="order-status">Status</label>
            <select class="form-select" id="order-status" name="status">
                <?php $totalOrders = array_sum($data['counts']); ?>
                <option value="">All orders<?= $totalOrders ? ' (' . e($totalOrders) . ')' : '' ?></option>
                <?php foreach (ORDER_STATUSES as $key => $label): $keyCount = $data['counts'][$key] ?? 0; ?><option value="<?= e($key) ?>"<?= selected($data['status'], $key) ?>><?= e($label) ?><?= $keyCount ? ' (' . e($keyCount) . ')' : '' ?></option><?php endforeach; ?>
            </select>
            <button type="submit" class="button button-primary">Filter</button>
            <a class="button button-outline" href="<?= e(url('vendor-orders', $data['status'] ? ['status' => $data['status']] : [])) ?>">Refresh orders</a>
        </form>
        <?php
        if (!$data['orders']):
            empty_state('No orders found', 'Orders for this kitchen appear here as students place them.', url('vendor-orders'), 'Show all orders', icon: 'orders');
        else:
            require __DIR__ . '/orders-table.php';
        endif;
        break;

    case 'vendor-menu':
        page_heading($title, $user['vendor_name'], 'vendor-item', 'Add menu item');
        if (!$data['items']):
            empty_state('Your menu is empty', 'Create a category and add the first item for your kitchen.', url('vendor-categories'), 'Manage categories', icon: 'dish');
            break;
        endif;
        ?>
        <div class="panel table-panel">
            <table class="data-table">
                <thead><tr><th scope="col">Item</th><th scope="col">Category</th><th scope="col">Price</th><th scope="col">Visibility</th><th scope="col">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($data['items'] as $record): ?>
                    <tr>
                        <th scope="row"><?= e($record['name']) ?><small><?= e($record['description']) ?></small></th>
                        <td><?= e($record['category_name']) ?></td><td><?= e(money(cents($record['price']))) ?></td>
                        <td><?php status_badge($record['is_active'] ? 'published' : 'hidden'); ?></td>
                        <td><div class="table-actions">
                            <a class="button button-outline button-small" href="<?= e(url('vendor-item', ['id' => $record['id']])) ?>">Edit</a>
                            <form method="post" action="index.php"><?php form_fields('item-status', (int) $record['id']); ?><button class="button button-outline button-small" type="submit" name="operation" value="<?= $record['is_active'] ? 'hide' : 'publish' ?>"><?= $record['is_active'] ? 'Hide' : 'Publish' ?></button></form>
                            <form method="post" action="index.php" data-confirm="Remove this menu item? Existing orders will keep their original details."><?php form_fields('remove-item', (int) $record['id']); ?><button class="button button-quiet button-small" type="submit">Remove</button></form>
                        </div></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php break;

    case 'vendor-categories':
        page_heading($title, $user['vendor_name'], 'vendor-category', 'Add category');
        if (!$data['categories']):
            empty_state('No categories yet', 'Add a category before publishing menu items.', url('vendor-category'), 'Add category', icon: 'tag');
            break;
        endif;
        ?>
        <div class="panel table-panel"><table class="data-table">
            <thead><tr><th scope="col">Category</th><th scope="col">Items</th><th scope="col">Actions</th></tr></thead>
            <tbody><?php foreach ($data['categories'] as $record): ?>
                <tr><th scope="row"><?= e($record['name']) ?></th><td><?= e($record['item_count']) ?></td><td><div class="table-actions">
                    <a class="button button-outline button-small" href="<?= e(url('vendor-category', ['id' => $record['id']])) ?>">Edit</a>
                    <form method="post" action="index.php" data-confirm="Remove this category?"><?php form_fields('remove-category', (int) $record['id']); ?><button class="button button-quiet button-small" type="submit">Remove</button></form>
                </div></td></tr>
            <?php endforeach; ?></tbody>
        </table></div>
        <?php break;

    case 'vendor-item':
    case 'vendor-category':
        $isItem = $page === 'vendor-item';
        $back = $isItem ? 'vendor-menu' : 'vendor-categories';
        ?>
        <a class="back-link" href="<?= e(url($back)) ?>"><?= $isItem ? 'Menu items' : 'Categories' ?></a>
        <?php page_heading($title, $user['vendor_name']); ?>
        <?php if ($isItem && !$data['categories']): ?>
            <?php empty_state('Add a category first', 'Every menu item belongs to a category.', url('vendor-category'), 'Add category', icon: 'tag'); ?>
        <?php else: ?>
            <form class="panel form-panel stack-form" method="post" action="index.php">
                <?php form_fields($isItem ? 'save-item' : 'save-category', $data['id']); ?>
                <div><?php field('name', $isItem ? 'Item name' : 'Category name', $input('name'), 'text', $isItem ? 100 : 60); ?></div>
                <?php if ($isItem): ?>
                    <div><label class="form-label" for="description">Description</label><textarea class="form-control" id="description" name="description" maxlength="500" rows="3" required><?= e($input('description')) ?></textarea></div>
                    <div class="form-columns">
                        <div><label class="form-label" for="category_id">Category</label><select class="form-select" id="category_id" name="category_id" required>
                            <option value="">Choose category</option><?php foreach ($data['categories'] as $category): ?><option value="<?= e($category['id']) ?>"<?= selected($input('category_id'), $category['id']) ?>><?= e($category['name']) ?></option><?php endforeach; ?>
                        </select></div>
                        <div><label class="form-label" for="price">Price (<?= e(config()['currency']) ?>)</label><input class="form-control" id="price" name="price" type="text" inputmode="decimal" maxlength="7" value="<?= e($input('price')) ?>" required></div>
                    </div>
                    <label class="check-label"><input type="checkbox" name="is_active" value="1"<?= checked($input('is_active', '1') === '1') ?>> Show this item on the student menu</label>
                <?php endif; ?>
                <div class="form-actions"><button class="button button-primary" type="submit"><?= $isItem ? 'Save item' : 'Save category' ?></button><a class="button button-outline" href="<?= e(url($back)) ?>">Cancel</a></div>
            </form>
        <?php endif; ?>
        <?php break;

    case 'admin-students':
        page_heading($title, (int) $data['totals']['active'] . ' active of ' . (int) $data['totals']['total'] . ' student accounts. Manage their access.');
        ?>
        <form class="toolbar filter-bar" method="get" action="index.php" role="search">
            <input type="hidden" name="page" value="admin-students">
            <label class="visually-hidden" for="student-search">Search for a student</label><input class="form-control" id="student-search" name="q" type="search" placeholder="Search username or email" maxlength="254" value="<?= e($data['q']) ?>">
            <label class="visually-hidden" for="student-status">Account status</label><select class="form-select" id="student-status" name="status">
                <option value="">All statuses</option><option value="active"<?= selected($data['status'], 'active') ?>>Active</option><option value="disabled"<?= selected($data['status'], 'disabled') ?>>Disabled</option>
            </select>
            <button class="button button-primary" type="submit">Search</button>
        </form>
        <?php if (!$data['students']): ?>
            <?php empty_state('No students found', 'Try a different search or clear your filters.', url('admin-students'), 'Clear filters', icon: 'users'); ?>
        <?php else: ?>
            <div class="panel table-panel"><table class="data-table">
                <thead><tr><th scope="col">Username</th><th scope="col">Email</th><th scope="col">Registered</th><th scope="col">Status</th><th scope="col">Actions</th></tr></thead>
                <tbody><?php foreach ($data['students'] as $record): ?>
                    <tr><th scope="row"><?= e($record['username']) ?></th><td><?= e($record['email']) ?></td><td><?= e(date_label($record['created_at'])) ?></td>
                        <td><?php status_badge($record['is_active'] ? 'active' : 'disabled'); ?></td>
                        <td><form method="post" action="index.php"><?php form_fields('student-status', (int) $record['id']); ?><button class="button button-outline button-small" type="submit" name="is_active" value="<?= $record['is_active'] ? 0 : 1 ?>"><?= $record['is_active'] ? 'Disable' : 'Enable' ?></button></form></td></tr>
                <?php endforeach; ?></tbody>
            </table></div>
        <?php endif; ?>
        <?php break;

    case 'admin-kitchens':
        page_heading($title, 'Manage campus kitchens and their pickup points.', 'admin-kitchen', 'Add kitchen');
        if (!$data['vendors']):
            empty_state('No kitchens yet', 'Add a kitchen to start setting up vendor accounts.', url('admin-kitchen'), 'Add kitchen', icon: 'store');
            break;
        endif;
        ?>
        <div class="panel table-panel"><table class="data-table">
            <thead><tr><th scope="col">Kitchen</th><th scope="col">Pickup location</th><th scope="col">Accounts</th><th scope="col">Status</th><th scope="col">Actions</th></tr></thead>
            <tbody><?php foreach ($data['vendors'] as $record): ?>
                <tr><th scope="row"><?= e($record['name']) ?></th><td><?= e($record['pickup_location']) ?></td><td><?= e($record['account_count']) ?></td>
                    <td><?php status_badge($record['is_active'] ? 'active' : 'disabled'); ?></td><td><a class="button button-outline button-small" href="<?= e(url('admin-kitchen', ['id' => $record['id']])) ?>">Edit</a></td></tr>
            <?php endforeach; ?></tbody>
        </table></div>
        <?php break;

    case 'admin-kitchen':
        ?>
        <a class="back-link" href="<?= e(url('admin-kitchens')) ?>">Kitchens</a>
        <?php page_heading($title); ?>
        <form class="panel form-panel stack-form" method="post" action="index.php">
            <?php form_fields('save-kitchen', $data['id']); ?>
            <div><?php field('name', 'Kitchen name', $input('name'), 'text', 100); ?></div>
            <div><?php field('pickup_location', 'Pickup location', $input('pickup_location'), 'text', 150); ?></div>
            <div><label class="check-label"><input type="checkbox" name="is_active" value="1"<?= checked($input('is_active', '1') === '1') ?>> Enable ordering and vendor access</label>
                <p class="form-hint">Disabling a kitchen hides its menu and blocks its vendor accounts. Students can still view their existing orders.</p></div>
            <div class="form-actions"><button class="button button-primary" type="submit">Save kitchen</button><a class="button button-outline" href="<?= e(url('admin-kitchens')) ?>">Cancel</a></div>
        </form>
        <?php break;

    case 'admin-vendor-accounts':
        page_heading($title, 'Create and manage sign-in accounts for kitchen staff.', 'admin-vendor-account', 'Add vendor account');
        if (!$data['accounts']):
            empty_state('No vendor accounts yet', 'Create an account and assign it to a kitchen.', url('admin-vendor-account'), 'Add vendor account', icon: 'users');
            break;
        endif;
        ?>
        <div class="panel table-panel"><table class="data-table">
            <thead><tr><th scope="col">Username</th><th scope="col">Email</th><th scope="col">Kitchen</th><th scope="col">Status</th><th scope="col">Actions</th></tr></thead>
            <tbody><?php foreach ($data['accounts'] as $record): ?>
                <tr><th scope="row"><?= e($record['username']) ?></th><td><?= e($record['email']) ?></td><td><?= e($record['vendor_name']) ?></td>
                    <td><?php status_badge(!$record['is_active'] ? 'disabled' : ($record['vendor_active'] ? 'active' : 'kitchen disabled')); ?></td>
                    <td><div class="table-actions">
                        <a class="button button-outline button-small" href="<?= e(url('admin-vendor-account', ['id' => $record['id']])) ?>">Edit</a>
                        <form method="post" action="index.php"><?php form_fields('vendor-account-status', (int) $record['id']); ?><button class="button button-outline button-small" type="submit" name="is_active" value="<?= $record['is_active'] ? 0 : 1 ?>"><?= $record['is_active'] ? 'Disable' : 'Enable' ?></button></form>
                    </div></td></tr>
            <?php endforeach; ?></tbody>
        </table></div>
        <?php break;

    case 'admin-vendor-account':
        $editing = $data['id'] > 0;
        ?>
        <a class="back-link" href="<?= e(url('admin-vendor-accounts')) ?>">Vendor accounts</a>
        <?php page_heading($title); ?>
        <?php if (!$data['vendors']): ?>
            <?php empty_state('Add a kitchen first', 'Every vendor account belongs to a kitchen.', url('admin-kitchen'), 'Add kitchen', icon: 'store'); ?>
        <?php else: ?>
            <form class="panel form-panel stack-form" method="post" action="index.php">
                <?php form_fields('save-vendor-account', $data['id']); ?>
                <div><?php field('username', 'Username', $input('username'), 'text', 30, 'off'); ?></div>
                <div><?php field('email', 'Email address', $input('email'), 'email', 254, 'off'); ?></div>
                <div><label class="form-label" for="vendor_id">Kitchen</label><select class="form-select" id="vendor_id" name="vendor_id"<?= $editing ? ' disabled' : ' required' ?>>
                    <option value="">Choose kitchen</option><?php foreach ($data['vendors'] as $vendor): ?><option value="<?= e($vendor['id']) ?>"<?= selected($input('vendor_id'), $vendor['id']) ?>><?= e($vendor['name'] . ($vendor['is_active'] ? '' : ' (disabled)')) ?></option><?php endforeach; ?>
                </select></div>
                <div><?php field('password', $editing ? 'New password' : 'Password', '', 'password', 72, 'new-password', !$editing); ?><div class="form-hint"><?= $editing ? 'Leave both password fields empty to keep the current password.' : 'At least 8 characters, with a number and a special character.' ?></div></div>
                <div><?php field('confirm_password', 'Confirm password', '', 'password', 72, 'new-password', !$editing); ?></div>
                <div class="form-actions"><button class="button button-primary" type="submit">Save account</button><a class="button button-outline" href="<?= e(url('admin-vendor-accounts')) ?>">Cancel</a></div>
            </form>
        <?php endif; ?>
        <?php break;
endswitch;

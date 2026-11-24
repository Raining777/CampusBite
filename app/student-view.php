<?php

declare(strict_types=1);

switch ($page):
    case 'menu':
        $filters = $data['filters'];
        $items = $data['items'];
        $filterParams = menu_parameters($filters);
        $baseFilters = array_diff_key($filterParams, ['category' => true]);
        ?>
        <?php if (!$filterParams): ?>
            <section class="hero">
                <div class="hero-copy">
                    <span class="hero-kicker"><span aria-hidden="true"></span> Campus kitchens</span>
                    <h1>Good food.<br><em>Right on campus.</em></h1>
                    <p>Order from your campus kitchens for pickup or delivery between classes.</p>
                    <a class="button button-lime" href="#campus-menu">Explore the menu</a>
                    <?php if ($data['vendors']): ?><span class="hero-note"><?= e(count($data['vendors'])) ?> <?= count($data['vendors']) === 1 ? 'kitchen' : 'kitchens' ?> serving now · pickup or delivery</span><?php endif; ?>
                </div>
                <img class="hero-image" src="assets/campus-bowl.webp" alt="Glazed chicken rice bowl with cucumber and fresh greens" fetchpriority="high">
            </section>
        <?php endif; ?>
        <section id="campus-menu" class="menu-section" aria-labelledby="menu-heading">
            <header class="menu-heading">
                <div>
                    <?php if ($filterParams): ?><h1 id="menu-heading">Campus menu</h1><?php else: ?><h2 id="menu-heading">Campus menu</h2><?php endif; ?>
                    <p><?= e(count($items)) ?> <?= count($items) === 1 ? 'item' : 'items' ?> available</p>
                </div>
                <form class="menu-filters" action="index.php" method="get" role="search">
                    <input type="hidden" name="page" value="menu">
                    <?php if ($filters['category'] !== ''): ?><input type="hidden" name="category" value="<?= e($filters['category']) ?>"><?php endif; ?>
                    <div class="search-field">
                        <label class="visually-hidden" for="menu-search">Search dishes</label>
                        <input class="form-control" id="menu-search" name="q" type="search" placeholder="Search dishes" maxlength="100" value="<?= e($filters['q']) ?>">
                    </div>
                    <label class="visually-hidden" for="menu-vendor">Kitchen</label>
                    <select class="form-select" id="menu-vendor" name="vendor">
                        <option value="0">All kitchens</option>
                        <?php foreach ($data['vendors'] as $vendor): ?><option value="<?= e($vendor['id']) ?>"<?= selected($filters['vendor'], $vendor['id']) ?>><?= e($vendor['name']) ?></option><?php endforeach; ?>
                    </select>
                    <button class="button button-primary" type="submit">Search</button>
                </form>
            </header>
            <nav class="category-nav" aria-label="Menu categories">
                <a class="category-pill" href="<?= e(url('menu', $baseFilters)) ?>#campus-menu"<?= $filters['category'] === '' ? ' aria-current="page"' : '' ?>>All dishes</a>
                <?php foreach ($data['categories'] as $category): ?>
                    <a class="category-pill" href="<?= e(url('menu', $baseFilters + ['category' => $category['name']])) ?>#campus-menu"<?= selected($filters['category'], $category['name']) ? ' aria-current="page"' : '' ?>><?= e($category['name']) ?></a>
                <?php endforeach; ?>
            </nav>
            <?php if (!$items): ?>
                <?php empty_state('No dishes found', 'Try a different search or clear your filters.', url('menu') . '#campus-menu', 'Clear filters', icon: 'bowl'); ?>
            <?php else: ?>
                <div class="menu-grid">
                    <?php foreach ($items as $item): ?>
                        <?php preg_match('/^./us', $item['name'], $initial); ?>
                        <article class="menu-card">
                            <?php if ($item['photo_slot']): ?>
                                <div class="dish-photo dish-photo-<?= e($item['photo_slot']) ?>" aria-hidden="true"><img src="assets/menu-photos.webp" alt="" loading="lazy" draggable="false"></div>
                            <?php else: ?>
                                <div class="dish-photo dish-photo-empty" data-initial="<?= e($initial[0] ?? 'C') ?>" aria-hidden="true"></div>
                            <?php endif; ?>
                            <div class="menu-card-copy">
                                <div class="dish-meta"><span><?= e($item['vendor_name']) ?></span><span><?= e($item['category_name']) ?></span></div>
                                <h3><?= e($item['name']) ?></h3>
                                <p><?= e($item['description']) ?></p>
                                <div class="dish-actions">
                                    <strong><?= e(money(cents($item['price']))) ?></strong>
                                    <?php if ($role === 'student'): ?>
                                        <form method="post" action="index.php">
                                            <?php form_fields('cart-add', (int) $item['id']); ?>
                                            <?php foreach ($filterParams as $name => $value): ?><input type="hidden" name="<?= e($name) ?>" value="<?= e($value) ?>"><?php endforeach; ?>
                                            <button class="button button-outline add-button" type="submit" aria-label="Add <?= e($item['name']) ?> to cart">Add <span aria-hidden="true">+</span></button>
                                        </form>
                                    <?php elseif (!$user): ?>
                                        <a class="button button-outline add-button" href="<?= e(url('login')) ?>" aria-label="Sign in to add <?= e($item['name']) ?>">Add <span aria-hidden="true">+</span></a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
        <?php break;

    case 'login':
    case 'register':
        $registration = $page === 'register';
        ?>
        <div class="auth-layout">
            <section class="auth-panel panel">
                <?php page_heading($title, $registration ? 'Create a student account to order from campus kitchens.' : 'Use your CampusBite email or username.'); ?>
                <form class="stack-form" method="post" action="index.php"<?= $registration ? ' data-strength' : '' ?>>
                    <?php form_fields($page); ?>
                    <?php if ($registration): ?>
                        <div><?php field('username', 'Username', $input('username'), 'text', 30, 'username'); ?><div class="form-hint">3–30 characters. Use letters, numbers, periods, underscores or hyphens.</div></div>
                        <div><?php field('email', 'Email address', $input('email'), 'email', 254, 'email'); ?></div>
                    <?php else: ?>
                        <div><?php field('identifier', 'Email or username', $input('identifier'), 'text', 254, 'username'); ?></div>
                    <?php endif; ?>
                    <div><?php field('password', 'Password', '', 'password', 72, $registration ? 'new-password' : 'current-password'); ?>
                        <?php if ($registration): ?>
                            <div class="form-hint">At least 8 characters, with a number and a special character.</div>
                            <div class="strength" data-level="0" aria-live="polite"><div class="strength-bars"><span></span><span></span><span></span><span></span></div><div class="strength-label"></div></div>
                        <?php endif; ?>
                    </div>
                    <?php if ($registration): ?><div><?php field('confirm_password', 'Confirm password', '', 'password', 72, 'new-password'); ?></div><?php endif; ?>
                    <button class="button button-primary" type="submit"><?= $registration ? 'Create account' : 'Sign in' ?></button>
                </form>
                <p class="auth-alternative"><?= $registration ? 'Already have an account?' : 'New to CampusBite?' ?>
                    <a href="<?= e(url($registration ? 'login' : 'register')) ?>"><?= $registration ? 'Sign in' : 'Create a student account' ?></a>
                </p>
            </section>
            <div class="auth-art" aria-hidden="true">
                <img src="assets/campus-bowl.webp" alt="">
                <blockquote>Lunch between lectures, <em>sorted</em>.</blockquote>
            </div>
        </div>
        <?php break;

    case 'cart-switch':
        $switch = $data['switch'];
        ?>
        <section class="panel form-panel">
            <?php page_heading($title); ?>
            <p>Your cart contains items from <strong><?= e($switch['cart_vendor']) ?></strong>. To add <strong><?= e($switch['item_name']) ?></strong> from <?= e($switch['vendor_name']) ?>, clear the current cart and start a new one.</p>
            <div class="form-actions">
                <form method="post" action="index.php"><?php form_fields('cart-switch', $switch['item_id']); ?><button class="button button-primary" type="submit">Clear cart and add item</button></form>
                <a class="button button-outline" href="<?= e(url('cart')) ?>">Keep my cart</a>
            </div>
        </section>
        <?php break;

    case 'cart':
        $items = $data['items'];
        page_heading($title, $items ? $items[0]['vendor_name'] : 'Items you add from the campus menu appear here.');
        if (!$items):
            empty_state('Your cart is empty', 'Browse the menu to add something from a campus kitchen.', url('menu'), 'Browse menu', icon: 'bag');
            break;
        endif;
        $hasUnavailableItems = in_array(0, array_column($items, 'available'));
        ?>
        <div class="content-split">
            <section class="panel list-panel" aria-label="Cart items">
                <?php foreach ($items as $item): ?>
                    <article class="cart-line">
                        <div><h2><?= e($item['name']) ?></h2><p><?= e(money(cents($item['price']))) ?> each</p><?php if (!$item['available']): ?><?php status_badge('unavailable'); ?><?php endif; ?></div>
                        <div class="cart-line-actions">
                            <?php if ($item['available']): ?>
                                <form method="post" action="index.php" class="inline-form quantity-form">
                                    <?php form_fields('cart-update', (int) $item['id']); ?>
                                    <label class="visually-hidden" for="quantity-<?= e($item['id']) ?>">Quantity for <?= e($item['name']) ?></label>
                                    <span class="qty-stepper">
                                        <button class="stepper-btn" type="button" data-step="-1" aria-label="Decrease quantity of <?= e($item['name']) ?>">&minus;</button>
                                        <input class="form-control" id="quantity-<?= e($item['id']) ?>" name="quantity" type="number" min="1" max="99" value="<?= e($item['quantity']) ?>" inputmode="numeric" required>
                                        <button class="stepper-btn" type="button" data-step="1" aria-label="Increase quantity of <?= e($item['name']) ?>">+</button>
                                    </span>
                                </form>
                            <?php endif; ?>
                            <form method="post" action="index.php" class="inline-form"><?php form_fields('cart-remove', (int) $item['id']); ?><button class="button button-quiet button-small" type="submit" aria-label="Remove <?= e($item['name']) ?>">Remove</button></form>
                        </div>
                        <strong><?= e(money(cents($item['price']) * (int) $item['quantity'])) ?></strong>
                    </article>
                <?php endforeach; ?>
            </section>
            <aside class="panel summary-panel">
                <h2>Order summary</h2>
                <div class="summary-total"><span>Subtotal</span><strong><?= e(money(cart_total($items))) ?></strong></div>
                <?php if ($hasUnavailableItems): ?><p class="form-hint">Remove unavailable items to continue to checkout.</p><?php else: ?><a class="button button-primary" href="<?= e(url('checkout')) ?>">Continue to checkout</a><?php endif; ?>
                <a class="summary-link" href="<?= e(url('menu')) ?>">Continue browsing</a>
            </aside>
        </div>
        <?php break;

    case 'checkout':
        $items = $data['items'];
        $fulfillment = $input('fulfillment', 'pickup');
        page_heading($title, $items[0]['vendor_name']);
        ?>
        <div class="content-split">
            <form class="panel form-panel stack-form" method="post" action="index.php" data-checkout>
                <?php form_fields('place-order'); ?>
                <input type="hidden" name="submission_key" value="<?= e($data['key']) ?>">
                <fieldset>
                    <legend>How would you like to get your order?</legend>
                    <label class="radio-option"><input type="radio" name="fulfillment" value="pickup"<?= checked($fulfillment !== 'delivery') ?>><span><strong>Self-pickup</strong><small><?= e($items[0]['pickup_location']) ?></small></span></label>
                    <label class="radio-option"><input type="radio" name="fulfillment" value="delivery"<?= checked($fulfillment === 'delivery') ?>><span><strong>Campus delivery</strong><small>Meet your kitchen at a campus location.</small></span></label>
                </fieldset>
                <div data-delivery-location>
                    <?php field('delivery_location', 'Campus delivery location', $input('delivery_location'), 'text', 150, 'off', false); ?>
                    <div class="form-hint">Include the building and a specific meeting point, such as Library, Level 1 entrance.</div>
                </div>
                <div class="form-actions"><button class="button button-primary" type="submit">Place order</button><a class="button button-outline" href="<?= e(url('cart')) ?>">Back to cart</a></div>
            </form>
            <aside class="panel summary-panel">
                <h2>Order summary</h2>
                <?php foreach ($items as $item): ?><div class="summary-line"><span><?= e($item['quantity']) ?> × <?= e($item['name']) ?></span><span><?= e(money(cents($item['price']) * (int) $item['quantity'])) ?></span></div><?php endforeach; ?>
                <div class="summary-total"><span>Total</span><strong><?= e(money(cart_total($items))) ?></strong></div>
            </aside>
        </div>
        <?php break;

    case 'orders':
        page_heading($title, 'Track orders from your campus kitchens.', 'menu', 'Browse menu');
        if (!$data['orders']):
            empty_state('No orders yet', 'Your orders and status updates will appear here.', icon: 'orders');
        else:
            require __DIR__ . '/orders-table.php';
        endif;
        break;

    case 'order':
        require __DIR__ . '/order-view.php';
        break;

    case 'message':
        ?><div class="message-glyph" aria-hidden="true">C<em>B</em></div><?php
        page_heading($title, $data['message']);
        ?><a class="button button-primary" href="<?= e(url($home)) ?>">Back to CampusBite</a><?php
        break;
endswitch;

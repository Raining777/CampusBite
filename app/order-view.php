<?php
$order = $data['order'];
$steps = order_steps($order['fulfillment']);
$next = next_status($order);
$currentStep = array_search($order['status'], $steps, true);
?>
<a class="back-link" href="<?= e(url($role === 'vendor' ? 'vendor-orders' : 'orders')) ?>">All orders</a>
<header class="page-heading">
    <div><h1><?= e($title) ?></h1><p><?= e($order['vendor_name']) ?> · <?= e(date_label($order['created_at'])) ?></p></div>
    <?php status_badge($order['status']); ?>
</header>
<div class="content-split">
    <div class="order-main">
        <section class="panel summary-panel">
            <h2>Order items</h2>
            <?php foreach ($order['items'] as $line): ?>
                <div class="summary-line"><span><?= e($line['quantity']) ?> × <?= e($line['item_name']) ?><small><?= e(money(cents($line['unit_price']))) ?> each</small></span><strong><?= e(money(cents($line['unit_price']) * (int) $line['quantity'])) ?></strong></div>
            <?php endforeach; ?>
            <div class="summary-total"><span>Total</span><strong><?= e(money(cents($order['total']))) ?></strong></div>
        </section>
        <section class="panel summary-panel">
            <h2>Order history</h2>
            <ol class="history-list">
                <?php foreach (array_reverse($order['history']) as $event): ?>
                    <li><strong><?= e(ORDER_STATUSES[$event['new_status']]) ?></strong><time datetime="<?= e(str_replace(' ', 'T', $event['changed_at']) . 'Z') ?>"><?= e(date_label($event['changed_at'])) ?></time></li>
                <?php endforeach; ?>
            </ol>
        </section>
    </div>
    <aside class="panel summary-panel">
        <h2>Order progress</h2>
        <ol class="order-steps">
            <?php foreach ($steps as $index => $step): ?>
                <li<?= $index === $currentStep ? ' aria-current="step"' : '' ?> class="<?= $index <= $currentStep ? 'step-reached' : '' ?>"><?= e(ORDER_STATUSES[$step]) ?></li>
            <?php endforeach; ?>
        </ol>
        <dl class="order-meta">
            <div><dt><?= e(order_method($order['fulfillment'])) ?></dt><dd><?= e($order['delivery_location'] ?? $order['pickup_location']) ?></dd></div>
            <?php if ($role === 'vendor'): ?><div><dt>Ordered by</dt><dd><?= e($order['student_name']) ?></dd></div><?php endif; ?>
        </dl>
        <?php if ($role === 'vendor' && $next): ?>
            <form method="post" action="index.php" class="stack-form">
                <?php form_fields('advance-order', (int) $order['id']); ?>
                <input type="hidden" name="status" value="<?= e($next) ?>">
                <button class="button button-primary" type="submit">Mark <?= e(ORDER_STATUSES[$next]) ?></button>
            </form>
        <?php elseif ($role === 'student'): ?>
            <a class="button button-outline" href="<?= e(url('order', ['id' => $order['id']])) ?>">Refresh status</a>
        <?php endif; ?>
    </aside>
</div>

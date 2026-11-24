<div class="panel table-panel">
    <table class="data-table">
        <thead><tr><th scope="col">Order</th><th scope="col"><?= $role === 'vendor' ? 'Student' : 'Kitchen' ?></th><th scope="col">Fulfilment</th><th scope="col">Status</th><th scope="col">Total</th></tr></thead>
        <tbody>
        <?php foreach ($data['orders'] as $order): ?>
            <tr>
                <th scope="row"><a href="<?= e(url('order', ['id' => $order['id']])) ?>"><?= e(order_number((int) $order['id'])) ?></a><small><?= e(date_label($order['created_at'])) ?></small></th>
                <td><?= e($role === 'vendor' ? $order['student_name'] : $order['vendor_name']) ?></td>
                <td><?= e(order_method($order['fulfillment'])) ?></td>
                <td><?php status_badge($order['status']); ?></td>
                <td><strong><?= e(money(cents($order['total']))) ?></strong></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

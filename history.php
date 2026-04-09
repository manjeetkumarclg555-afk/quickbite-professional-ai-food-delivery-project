<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

require_login();

$userId = (int) current_user()['id'];
$orders = get_user_order_history($conn, $userId);
$notifications = fetch_user_notifications($conn, $userId, 6);
if ($notifications !== []) {
    mark_notifications_read($conn, $userId);
}
$totalSpent = 0.0;
$deliveredCount = 0;
$activeOrders = 0;

foreach ($orders as $order) {
    $totalSpent += (float) $order['total'];
    if ($order['status'] === 'Delivered') {
        $deliveredCount++;
    }
    if (in_array($order['status'], ['Pending Payment', 'Placed', 'Paid', 'Preparing', 'Out for Delivery'], true)) {
        $activeOrders++;
    }
}

render_header('Order History');
?>
<section class="page-banner">
    <div>
        <p class="eyebrow">Customer History</p>
        <h1>Track your past orders, delivery performance, and service quality.</h1>
        <p class="hero-text">Every completed order contributes to a cleaner operations history and a richer delivery dataset.</p>
    </div>
</section>

<section class="kpi-grid compact-grid">
    <article class="kpi-card">
        <span class="kpi-label">Total Orders</span>
        <strong class="kpi-number"><?php echo count($orders); ?></strong>
    </article>
    <article class="kpi-card">
        <span class="kpi-label">Delivered Orders</span>
        <strong class="kpi-number"><?php echo $deliveredCount; ?></strong>
    </article>
    <article class="kpi-card">
        <span class="kpi-label">Lifetime Spend</span>
        <strong class="kpi-number"><?php echo h(format_price($totalSpent)); ?></strong>
    </article>
    <article class="kpi-card">
        <span class="kpi-label">Active Orders</span>
        <strong class="kpi-number"><?php echo $activeOrders; ?></strong>
    </article>
</section>

<section class="analytics-grid">
    <article class="info-card slim-card">
        <p class="eyebrow">Notifications</p>
        <h2>Order update timeline</h2>
        <?php if ($notifications === []): ?>
            <p class="muted">No order notifications yet. Updates appear here when your order is placed, paid, dispatched, or delivered.</p>
        <?php else: ?>
            <div class="notification-list">
                <?php foreach ($notifications as $notification): ?>
                    <div class="notification-card notification-<?php echo h($notification['severity']); ?>">
                        <div class="history-top-row">
                            <strong><?php echo h($notification['title']); ?></strong>
                            <span class="cell-meta"><?php echo h(format_datetime($notification['created_at'])); ?></span>
                        </div>
                        <p><?php echo h($notification['message']); ?></p>
                        <?php if ($notification['order_id'] !== null): ?>
                            <span class="cell-meta">Order #<?php echo (int) $notification['order_id']; ?></span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </article>
    <article class="info-card slim-card">
        <p class="eyebrow">Service Quality</p>
        <h2>Performance summary from your history</h2>
        <div class="mini-table">
            <div class="mini-table-row">
                <div><strong>Completion rate</strong></div>
                <div class="mini-table-metric"><strong><?php echo count($orders) > 0 ? h(number_format(($deliveredCount / count($orders)) * 100, 1)) : '0.0'; ?>%</strong></div>
            </div>
            <div class="mini-table-row">
                <div><strong>Average ticket</strong></div>
                <div class="mini-table-metric"><strong><?php echo count($orders) > 0 ? h(format_price($totalSpent / count($orders))) : h(format_price(0.0)); ?></strong></div>
            </div>
            <div class="mini-table-row">
                <div><strong>Recent activity</strong></div>
                <div class="mini-table-metric"><strong><?php echo $activeOrders > 0 ? $activeOrders . ' live' : 'All clear'; ?></strong></div>
            </div>
        </div>
    </article>
</section>

<?php if (empty($orders)): ?>
    <section class="empty-state">
        <h2>No order history yet</h2>
        <p>Place your first order to start building your delivery timeline.</p>
        <a class="button primary" href="<?php echo h(app_path('menu.php')); ?>">Browse Menu</a>
    </section>
<?php else: ?>
    <section class="history-grid">
        <?php foreach ($orders as $order): ?>
            <article class="history-card">
                <div class="history-top-row">
                    <div>
                        <p class="eyebrow">Order #<?php echo (int) $order['id']; ?></p>
                        <h2><?php echo h(format_price((float) $order['total'])); ?></h2>
                    </div>
                    <span class="<?php echo h(status_badge_class($order['status'])); ?>"><?php echo h($order['status']); ?></span>
                </div>
                <div class="history-meta-grid">
                    <div>
                        <span class="kpi-label">Placed On</span>
                        <strong><?php echo h(format_datetime($order['created_at'])); ?></strong>
                    </div>
                    <div>
                        <span class="kpi-label">City</span>
                        <strong><?php echo h($order['delivery_city'] ?? 'Bengaluru'); ?></strong>
                    </div>
                    <div>
                        <span class="kpi-label">Zone</span>
                        <strong><?php echo h($order['delivery_zone']); ?></strong>
                    </div>
                    <div>
                        <span class="kpi-label">ETA</span>
                        <strong><?php echo h(format_minutes($order['estimated_delivery_minutes'])); ?></strong>
                    </div>
                    <div>
                        <span class="kpi-label">Actual Delivery</span>
                        <strong><?php echo h(format_minutes($order['actual_delivery_minutes'])); ?></strong>
                    </div>
                    <div>
                        <span class="kpi-label">Items</span>
                        <strong><?php echo $order['item_names'] !== '' ? h($order['item_names']) : ((int) $order['total_items'] . ' total / ' . (int) $order['unique_items'] . ' unique'); ?></strong>
                        <?php if ($order['item_names'] !== ''): ?>
                            <span class="cell-meta"><?php echo (int) $order['total_items']; ?> total / <?php echo (int) $order['unique_items']; ?> unique</span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <span class="kpi-label">Rating</span>
                        <strong><?php echo $order['customer_rating'] !== null ? h(number_format((float) $order['customer_rating'], 1)) . ' / 5' : 'Pending'; ?></strong>
                    </div>
                </div>
                <p class="muted">Payment: <?php echo h($order['payment_method']); ?> | Traffic: <?php echo h($order['traffic_level']); ?> | Weather: <?php echo h($order['weather_condition']); ?></p>
            </article>
        <?php endforeach; ?>
    </section>
<?php endif; ?>
<?php render_footer(); ?>

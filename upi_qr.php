<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

require_login();

if (!isset($_SESSION['current_order_id'])) {
    flash('error', 'No pending order.');
    redirect('history.php');
}

$orderId = (int) $_SESSION['current_order_id'];
unset($_SESSION['current_order_id']);

$stmt = $conn->prepare(
    'SELECT id, total, delivery_city, delivery_zone, estimated_delivery_minutes
     FROM orders
     WHERE id = ? AND user_id = ?
     LIMIT 1'
);
$userId = (int) current_user()['id'];
$stmt->bind_param('ii', $orderId, $userId);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    flash('error', 'Invalid order.');
    redirect('history.php');
}

$order['total'] = (float) $order['total'];
$upiId = 'testmerchant@payu';
$upiPayeeName = app_name();
$upiAmount = number_format($order['total'], 2, '.', '');
$upiNote = 'Order ' . $orderId;
$upiUri = 'upi://pay?pa=' . rawurlencode($upiId)
    . '&pn=' . rawurlencode($upiPayeeName)
    . '&am=' . rawurlencode($upiAmount)
    . '&cu=INR'
    . '&tn=' . rawurlencode($upiNote);
$qrUrl = 'https://quickchart.io/qr?text=' . rawurlencode($upiUri) . '&size=280';

render_header('UPI Payment');
?>
<section class="page-banner">
    <div>
        <p class="eyebrow">UPI Checkout</p>
        <h1>Complete payment for Order #<?php echo (int) $orderId; ?></h1>
        <p class="hero-text">Scan the QR with PhonePe, Google Pay, or Paytm, then confirm the UPI transaction ID to move your order forward.</p>
    </div>
</section>

<section class="checkout-layout upi-payment-layout">
    <article class="checkout-card upi-payment-card">
        <div class="upi-hero-row">
            <div>
                <p class="eyebrow">Scan And Pay</p>
                <h2><?php echo h(format_price($order['total'])); ?></h2>
                <p class="muted">UPI ID: <?php echo h($upiId); ?></p>
            </div>
            <div class="upi-status-pill">Secure UPI Flow</div>
        </div>

        <div class="qr-code-card">
            <img class="upi-qr-image" src="<?php echo h($qrUrl); ?>" alt="UPI QR Code for Order <?php echo (int) $orderId; ?>">
        </div>

        <div class="upi-step-list">
            <div class="upi-step">
                <strong>1.</strong>
                <span>Open PhonePe, GPay, Paytm, or any UPI app.</span>
            </div>
            <div class="upi-step">
                <strong>2.</strong>
                <span>Scan the QR and pay <?php echo h(format_price($order['total'])); ?>.</span>
            </div>
            <div class="upi-step">
                <strong>3.</strong>
                <span>Paste the payment transaction ID below and confirm.</span>
            </div>
        </div>

        <form class="upi-confirm-form" method="post" action="confirm_upi.php">
            <input type="hidden" name="order_id" value="<?php echo (int) $orderId; ?>">
            <label>
                <span>UPI Transaction ID</span>
                <input type="text" name="payment_id" placeholder="Enter UPI Txn ID after payment" required>
            </label>
            <div class="upi-action-row">
                <button type="submit" class="button primary">Confirm Payment</button>
                <a href="<?php echo h(app_path('history.php')); ?>" class="button secondary">Back to History</a>
            </div>
        </form>
    </article>

    <aside class="summary-card upi-summary-card">
        <h2>Payment Summary</h2>
        <div class="summary-row"><span>Order ID</span><strong>#<?php echo (int) $orderId; ?></strong></div>
        <div class="summary-row"><span>Amount</span><strong><?php echo h(format_price($order['total'])); ?></strong></div>
        <div class="summary-row"><span>Delivery city</span><strong><?php echo h((string) $order['delivery_city']); ?></strong></div>
        <div class="summary-row"><span>Delivery zone</span><strong><?php echo h((string) $order['delivery_zone']); ?></strong></div>
        <div class="summary-row"><span>ETA after confirmation</span><strong><?php echo h(format_minutes((int) $order['estimated_delivery_minutes'])); ?></strong></div>
        <div class="summary-row"><span>Accepted apps</span><strong>PhonePe, GPay, Paytm</strong></div>
        <div class="summary-row total"><span>Pay now</span><strong><?php echo h(format_price($order['total'])); ?></strong></div>
    </aside>
</section>
<?php render_footer(); ?>

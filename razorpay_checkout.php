<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/razorpay.php';

require_login();

if (!razorpay_is_configured()) {
    flash('error', 'Razorpay keys are missing. Please configure Razorpay first.');
    redirect('order.php');
}

$orderId = (int) ($_SESSION['current_order_id'] ?? 0);
if ($orderId <= 0) {
    flash('error', 'No pending Razorpay order found.');
    redirect('history.php');
}

$stmt = $conn->prepare(
    'SELECT id, total, status, payment_method, payment_status, gateway_order_id, delivery_city, delivery_zone
     FROM orders
     WHERE id = ? AND user_id = ?
     LIMIT 1'
);
$userId = (int) current_user()['id'];
$stmt->bind_param('ii', $orderId, $userId);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order || $order['payment_method'] !== 'Razorpay') {
    unset($_SESSION['current_order_id']);
    flash('error', 'This order is not available for Razorpay payment.');
    redirect('history.php');
}

if (($order['payment_status'] ?? '') === 'paid') {
    unset($_SESSION['current_order_id']);
    flash('success', 'This order is already paid.');
    redirect('history.php');
}

if (($order['gateway_order_id'] ?? '') === '') {
    flash('error', 'Razorpay order token is missing. Please try checkout again.');
    redirect('order.php');
}

$summary = cart_summary($conn, $userId);

render_header('Razorpay Checkout');
?>
<section class="checkout-layout">
    <article class="checkout-card">
        <p class="eyebrow">Razorpay Payment</p>
        <h1>Complete your secure payment</h1>
        <p class="muted">Order #<?php echo (int) $order['id']; ?> is waiting for Razorpay confirmation. Use the button below to open the Razorpay payment window.</p>
        <div class="summary-row"><span>Delivery city</span><strong><?php echo h((string) $order['delivery_city']); ?></strong></div>
        <div class="summary-row"><span>Delivery zone</span><strong><?php echo h((string) $order['delivery_zone']); ?></strong></div>
        <div class="summary-row total"><span>Total payable</span><strong><?php echo h(format_price((float) $order['total'])); ?></strong></div>
        <button class="button primary full-width" id="rzp-pay-button" type="button">Pay with Razorpay</button>
        <p class="muted" id="rzp-status-message" hidden></p>
        <a class="button ghost full-width" href="<?php echo h(app_path('history.php')); ?>">Go to History</a>
        <form id="razorpay-verify-form" method="post" action="<?php echo h(app_path('verify_payment.php')); ?>" hidden>
            <input type="hidden" name="local_order_id" value="<?php echo (int) $order['id']; ?>">
            <input type="hidden" name="razorpay_order_id" id="razorpay-order-id" value="<?php echo h((string) $order['gateway_order_id']); ?>">
            <input type="hidden" name="razorpay_payment_id" id="razorpay-payment-id" value="">
            <input type="hidden" name="razorpay_signature" id="razorpay-signature" value="">
        </form>
    </article>

    <aside class="summary-card">
        <h2>Current cart preview</h2>
        <?php foreach ($summary['items'] as $item): ?>
            <div class="summary-row">
                <span><?php echo h($item['name'] . ' x' . $item['quantity']); ?></span>
                <strong><?php echo h(format_price((float) $item['line_total'])); ?></strong>
            </div>
        <?php endforeach; ?>
        <div class="summary-row"><span>Subtotal</span><strong><?php echo h(format_price((float) $summary['subtotal'])); ?></strong></div>
        <div class="summary-row"><span>Delivery Fee</span><strong><?php echo h(format_price((float) $summary['delivery_fee'])); ?></strong></div>
        <div class="summary-row"><span>Tax</span><strong><?php echo h(format_price((float) $summary['tax'])); ?></strong></div>
        <div class="summary-row total"><span>Total</span><strong><?php echo h(format_price((float) $order['total'])); ?></strong></div>
    </aside>
</section>
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
(() => {
    const payButton = document.getElementById('rzp-pay-button');
    const verifyForm = document.getElementById('razorpay-verify-form');
    const paymentIdInput = document.getElementById('razorpay-payment-id');
    const signatureInput = document.getElementById('razorpay-signature');
    const statusMessage = document.getElementById('rzp-status-message');

    const showStatus = (message) => {
        if (!statusMessage) {
            return;
        }

        statusMessage.hidden = false;
        statusMessage.textContent = message;
    };

    if (!payButton || !verifyForm || !paymentIdInput || !signatureInput) {
        return;
    }

    if (typeof Razorpay === 'undefined') {
        payButton.disabled = true;
        showStatus('Razorpay checkout could not load. Refresh the page and make sure you are online, then try again.');
        return;
    }

    const options = {
        key: <?php echo json_encode(razorpay_key_id()); ?>,
        amount: <?php echo json_encode((int) round(((float) $order['total']) * 100)); ?>,
        currency: <?php echo json_encode(razorpay_currency()); ?>,
        name: <?php echo json_encode(app_name()); ?>,
        description: <?php echo json_encode('Payment for Order #' . (int) $order['id']); ?>,
        order_id: <?php echo json_encode((string) $order['gateway_order_id']); ?>,
        handler: function (response) {
            paymentIdInput.value = response.razorpay_payment_id || '';
            signatureInput.value = response.razorpay_signature || '';
            verifyForm.submit();
        },
        prefill: {
            name: <?php echo json_encode((string) current_user()['name']); ?>,
            email: <?php echo json_encode((string) current_user()['email']); ?>
        },
        theme: {
            color: '#440f9a'
        },
        modal: {
            ondismiss: function () {
                window.location.href = <?php echo json_encode(app_path('history.php')); ?>;
            }
        }
    };

    const razorpay = new Razorpay(options);
    payButton.addEventListener('click', () => {
        payButton.disabled = true;

        try {
            razorpay.open();
        } catch (error) {
            payButton.disabled = false;
            showStatus('Unable to open Razorpay checkout right now. Please refresh the page and try again.');
        }
    });

    razorpay.on('payment.failed', function (response) {
        payButton.disabled = false;
        const description = response && response.error && response.error.description
            ? response.error.description
            : 'Payment failed before completion. Please try again.';
        showStatus(description);
    });
})();
</script>
<?php render_footer(); ?>

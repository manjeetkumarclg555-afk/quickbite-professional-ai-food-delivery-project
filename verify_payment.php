<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/intelligence.php';
require_once __DIR__ . '/includes/razorpay.php';
require_once __DIR__ . '/includes/contact_notifications.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('history.php');
}

$localOrderId = (int) ($_POST['local_order_id'] ?? 0);
$razorpayOrderId = trim((string) ($_POST['razorpay_order_id'] ?? ''));
$paymentId = trim((string) ($_POST['razorpay_payment_id'] ?? ''));
$signature = trim((string) ($_POST['razorpay_signature'] ?? ''));
$userId = (int) current_user()['id'];

if ($localOrderId <= 0 || $razorpayOrderId === '' || $paymentId === '' || $signature === '') {
    flash('error', 'Incomplete Razorpay payment details.');
    redirect('history.php');
}

if (!razorpay_verify_payment_signature($razorpayOrderId, $paymentId, $signature)) {
    flash('error', 'Razorpay signature verification failed.');
    redirect('history.php');
}

$stmt = $conn->prepare(
    'UPDATE orders
     SET payment_status = "paid",
         gateway_order_id = ?,
         gateway_payment_id = ?,
         gateway_signature = ?,
         status = "Paid"
     WHERE id = ? AND user_id = ? AND payment_method = "Razorpay"'
);
$stmt->bind_param('sssii', $razorpayOrderId, $paymentId, $signature, $localOrderId, $userId);
$stmt->execute();
$updated = $stmt->affected_rows > 0;
$stmt->close();

if (!$updated) {
    flash('error', 'Order not found for Razorpay verification.');
    redirect('history.php');
}

$clearStmt = $conn->prepare('DELETE FROM cart WHERE user_id = ?');
$clearStmt->bind_param('i', $userId);
$clearStmt->execute();
$clearStmt->close();

$orderStmt = $conn->prepare('SELECT estimated_delivery_minutes FROM orders WHERE id = ? AND user_id = ? LIMIT 1');
$orderStmt->bind_param('ii', $localOrderId, $userId);
$orderStmt->execute();
$order = $orderStmt->get_result()->fetch_assoc();
$orderStmt->close();

create_notification(
    $conn,
    $userId,
    $localOrderId,
    'payment_confirmed',
    'Razorpay payment received',
    'Order #' . $localOrderId . ' was paid successfully through Razorpay. ETA: ' . (int) ($order['estimated_delivery_minutes'] ?? 0) . ' minutes.',
    'success'
);

notify_order_event(
    (string) current_user()['name'],
    (string) current_user()['email'],
    (string) (current_user()['phone'] ?? ''),
    $localOrderId,
    'paid',
    'Estimated delivery: ' . (int) ($order['estimated_delivery_minutes'] ?? 0) . ' minutes.'
);

unset($_SESSION['current_order_id']);

flash('success', 'Razorpay payment successful for Order #' . $localOrderId . '.');
redirect('history.php');

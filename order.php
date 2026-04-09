<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/razorpay.php';
require_once __DIR__ . '/includes/contact_notifications.php';

require_login();

$userId = (int) current_user()['id'];
$summary = cart_summary($conn, $userId);
$itemCount = 0;
foreach ($summary['items'] as $item) {
    $itemCount += (int) $item['quantity'];
}

if (empty($summary['items'])) {
    flash('error', 'Your cart is empty.');
    redirect('menu.php');
}

$selectedCity = trim((string) ($_POST['delivery_city'] ?? $_GET['delivery_city'] ?? current_delivery_city()));
$selectedZone = trim((string) ($_POST['delivery_zone'] ?? $_GET['delivery_zone'] ?? 'Central'));
$paymentMethod = trim($_POST['payment_method'] ?? 'Cash on Delivery');
$cityOptions = delivery_city_options();
if (!in_array($selectedCity, $cityOptions, true)) {
    $selectedCity = 'Bengaluru';
}

$zoneOptions = delivery_zone_options($selectedCity);
if (!in_array($selectedZone, $zoneOptions, true)) {
    $selectedZone = $zoneOptions[0] ?? 'Central';
}

$deliveryProfile = estimate_delivery_profile($selectedCity, $selectedZone, $itemCount);
$cityProfiles = delivery_city_profiles();


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $address = trim($_POST['address'] ?? '');
    $paymentMethod = trim($_POST['payment_method'] ?? 'Cash on Delivery');
    $specialInstructions = trim($_POST['special_instructions'] ?? '');

    if ($address === '') {
        flash('error', 'Delivery address is required.');
        redirect('order.php');
    }

    if ($paymentMethod === 'Razorpay' && !razorpay_is_configured()) {
        flash('error', 'Razorpay is not configured yet. Please add your Razorpay keys first.');
        redirect('order.php');
    }

    $requiresOnlinePayment = in_array($paymentMethod, ['UPI QR', 'Razorpay'], true);
    $status = $requiresOnlinePayment ? 'Pending Payment' : 'Placed';
    $subtotal = $summary['subtotal'];
    $deliveryFee = $summary['delivery_fee'];
    $taxAmount = $summary['tax'];
    $total = $summary['grand_total'];
    $distanceKm = $deliveryProfile['distance_km'];
    $estimatedMinutes = $deliveryProfile['estimated_delivery_minutes'];
    $prepTimeMinutes = $deliveryProfile['prep_time_minutes'];
    $trafficLevel = $deliveryProfile['traffic_level'];
    $weatherCondition = $deliveryProfile['weather_condition'];
    $paymentStatus = $paymentMethod === 'Razorpay' ? 'created' : 'pending';

    $stmt = $conn->prepare(
        'INSERT INTO orders(
            user_id, subtotal, delivery_fee, tax_amount, total, status, delivery_address,
            payment_method, payment_status, delivery_city, delivery_zone, distance_km, estimated_delivery_minutes,
            actual_delivery_minutes, prep_time_minutes, traffic_level, weather_condition,
            customer_rating, special_instructions, delivered_at
         ) VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, ?, ?, ?, NULL, ?, NULL)'
    );
    $stmt->bind_param(
        'iddddssssssdiisss',
        $userId,
        $subtotal,
        $deliveryFee,
        $taxAmount,
        $total,
        $status,
        $address,
        $paymentMethod,
        $paymentStatus,
        $selectedCity,
        $selectedZone,
        $distanceKm,
        $estimatedMinutes,
        $prepTimeMinutes,
        $trafficLevel,
        $weatherCondition,
        $specialInstructions
    );
    $stmt->execute();
    $orderId = $stmt->insert_id;
    $stmt->close();

    $itemStmt = $conn->prepare(
        'INSERT INTO order_items(order_id, food_id, quantity, price)
         VALUES(?, ?, ?, ?)'
    );

    foreach ($summary['items'] as $item) {
        $foodId = (int) $item['food_id'];
        $qty = (int) $item['quantity'];
        $price = (float) $item['price'];
        $itemStmt->bind_param('iiid', $orderId, $foodId, $qty, $price);
        $itemStmt->execute();
    }

    $itemStmt->close();

    if (!$requiresOnlinePayment) {
        $clearStmt = $conn->prepare('DELETE FROM cart WHERE user_id = ?');
        $clearStmt->bind_param('i', $userId);
        $clearStmt->execute();
        $clearStmt->close();

        create_notification(
            $conn,
            $userId,
            $orderId,
            'order_placed',
            'Order placed successfully',
            'Order #' . $orderId . ' is confirmed with an ETA of ' . $estimatedMinutes . ' minutes.',
            'success'
        );

        notify_order_event(
            (string) current_user()['name'],
            (string) current_user()['email'],
            (string) (current_user()['phone'] ?? ''),
            $orderId,
            'confirmed',
            'Estimated delivery: ' . $estimatedMinutes . ' minutes.'
        );

        flash('success', 'Order #' . $orderId . ' placed successfully. Delivery ETA: ' . $estimatedMinutes . ' minutes.');
    }

    if ($paymentMethod === 'UPI QR') {
        $_SESSION['current_order_id'] = $orderId;
        flash('success', 'Order #' . $orderId . ' created. Complete UPI payment to confirm it.');
        redirect('upi_qr.php');
    } elseif ($paymentMethod === 'Razorpay') {
        try {
            $razorpayOrder = razorpay_create_order(
                'quickbite-order-' . $orderId,
                (int) round($total * 100),
                [
                    'local_order_id' => (string) $orderId,
                    'customer_id' => (string) $userId,
                    'city' => $selectedCity,
                ]
            );
        } catch (RuntimeException $exception) {
            $rollbackStmt = $conn->prepare('DELETE FROM order_items WHERE order_id = ?');
            $rollbackStmt->bind_param('i', $orderId);
            $rollbackStmt->execute();
            $rollbackStmt->close();

            $deleteOrderStmt = $conn->prepare('DELETE FROM orders WHERE id = ? AND user_id = ?');
            $deleteOrderStmt->bind_param('ii', $orderId, $userId);
            $deleteOrderStmt->execute();
            $deleteOrderStmt->close();

            flash('error', $exception->getMessage());
            redirect('order.php');
        }

        $gatewayOrderId = (string) $razorpayOrder['id'];
        $gatewayStmt = $conn->prepare('UPDATE orders SET gateway_order_id = ? WHERE id = ? AND user_id = ?');
        $gatewayStmt->bind_param('sii', $gatewayOrderId, $orderId, $userId);
        $gatewayStmt->execute();
        $gatewayStmt->close();

        $_SESSION['current_order_id'] = $orderId;
        flash('success', 'Order #' . $orderId . ' created. Complete Razorpay payment to confirm it.');
        redirect('razorpay_checkout.php');
    } else {
        redirect('history.php');
    }
}


render_header('Checkout');
?>
<section class="checkout-layout">
    <form class="checkout-card" method="post">
        <p class="eyebrow">Checkout</p>
        <h1>Complete your delivery details</h1>
        <label>
            <span>Delivery city</span>
            <div class="location-select-shell">
                <span class="location-select-icon" aria-hidden="true"></span>
                <select name="delivery_city" id="delivery-city-select" class="location-select-input">
                    <?php foreach ($cityOptions as $city): ?>
                        <option value="<?php echo h($city); ?>" <?php echo $selectedCity === $city ? 'selected' : ''; ?>>
                            <?php echo h($city); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </label>
        <label>
            <span>Delivery address</span>
            <textarea name="address" rows="4" placeholder="House no, street, area, landmark" required></textarea>
        </label>
        <label>
            <span>Delivery zone</span>
            <select name="delivery_zone" id="delivery-zone-select">
                <?php foreach ($zoneOptions as $zone): ?>
                    <option value="<?php echo h($zone); ?>" <?php echo $selectedZone === $zone ? 'selected' : ''; ?>>
                        <?php echo h($zone); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <span>Payment method</span>
            <select name="payment_method">
                <?php foreach (payment_method_options() as $method): ?>
                    <option value="<?php echo h($method); ?>" <?php echo $paymentMethod === $method ? 'selected' : ''; ?>><?php echo h($method); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <span>Special instructions</span>
            <textarea name="special_instructions" rows="3" placeholder="Gate code, landmark, cutlery preference, or rider note"></textarea>
        </label>
        <br>
        <button class="button primary" type="submit">Place Order</button>

    </form>


    <aside class="summary-card">
        <h2>Operational Summary</h2>
        <?php foreach ($summary['items'] as $item): ?>
            <div class="summary-row">
                <span><?php echo h($item['name'] . ' x' . $item['quantity']); ?></span>
                <strong><?php echo h(format_price((float) $item['line_total'])); ?></strong>
            </div>
        <?php endforeach; ?>
        <div class="summary-row"><span>Subtotal</span><strong><?php echo h(format_price((float) $summary['subtotal'])); ?></strong></div>
        <div class="summary-row"><span>Delivery Fee</span><strong><?php echo h(format_price((float) $summary['delivery_fee'])); ?></strong></div>
        <div class="summary-row"><span>Tax</span><strong><?php echo h(format_price((float) $summary['tax'])); ?></strong></div>
        <div class="summary-row"><span>Delivery city</span><strong id="summary-delivery-city"><?php echo h($selectedCity); ?></strong></div>
        <div class="summary-row"><span>Delivery zone</span><strong id="summary-delivery-zone"><?php echo h($selectedZone); ?></strong></div>
        <div class="summary-row"><span>Estimated ETA</span><strong id="summary-delivery-eta"><?php echo h(format_minutes((int) $deliveryProfile['estimated_delivery_minutes'])); ?></strong></div>
        <div class="summary-row"><span>Distance</span><strong id="summary-delivery-distance"><?php echo h(number_format((float) $deliveryProfile['distance_km'], 1)); ?> km</strong></div>
        <div class="summary-row"><span>Traffic</span><strong id="summary-delivery-traffic"><?php echo h($deliveryProfile['traffic_level']); ?></strong></div>
        <div class="summary-row total"><span>Total Payable</span><strong><?php echo h(format_price((float) $summary['grand_total'])); ?></strong></div>
    </aside>
</section>
<script>
(() => {
    const citySelect = document.getElementById('delivery-city-select');
    const zoneSelect = document.getElementById('delivery-zone-select');
    const cityProfiles = <?php echo json_encode($cityProfiles, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    const summaryCity = document.getElementById('summary-delivery-city');
    const summaryZone = document.getElementById('summary-delivery-zone');
    const summaryEta = document.getElementById('summary-delivery-eta');
    const summaryDistance = document.getElementById('summary-delivery-distance');
    const summaryTraffic = document.getElementById('summary-delivery-traffic');
    const currentTraffic = <?php echo json_encode($deliveryProfile['traffic_level']); ?>;
    const itemCount = <?php echo json_encode($itemCount); ?>;

    if (!citySelect || !zoneSelect) {
        return;
    }

    const renderSummary = () => {
        const city = citySelect.value;
        const zone = zoneSelect.value;
        const profiles = cityProfiles[city] || cityProfiles.Bengaluru || {};
        const profile = profiles[zone] || profiles.Central;
        if (!profile) {
            return;
        }

        const trafficAdjustment = {Low: 0, Medium: 4, High: 8}[currentTraffic] ?? 4;
        const eta = Math.round(profile.eta + trafficAdjustment + Math.max(0, itemCount - 2) * 1.5);

        if (summaryCity) {
            summaryCity.textContent = city;
        }
        if (summaryZone) {
            summaryZone.textContent = zone;
        }
        if (summaryEta) {
            summaryEta.textContent = `${eta} min`;
        }
        if (summaryDistance) {
            summaryDistance.textContent = `${Number(profile.distance_km).toFixed(1)} km`;
        }
        if (summaryTraffic) {
            summaryTraffic.textContent = currentTraffic;
        }
    };

    const syncZones = () => {
        const city = citySelect.value;
        const profiles = cityProfiles[city] || cityProfiles.Bengaluru || {};
        const zones = Object.keys(profiles);
        const previousValue = zoneSelect.value;
        zoneSelect.replaceChildren();

        zones.forEach((zone, index) => {
            const option = document.createElement('option');
            option.value = zone;
            option.textContent = zone;
            option.selected = previousValue === zone || (previousValue === '' && index === 0);
            zoneSelect.appendChild(option);
        });

        if (!zones.includes(previousValue) && zones.length > 0) {
            zoneSelect.value = zones[0];
        }

        renderSummary();
    };

    citySelect.addEventListener('change', syncZones);
    zoneSelect.addEventListener('change', renderSummary);
    syncZones();
})();
</script>
<?php render_footer(); ?>

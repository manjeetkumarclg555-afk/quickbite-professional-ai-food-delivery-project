<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

require_login();

$userId = (int) current_user()['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $foodId = (int) ($_POST['food_id'] ?? 0);

    if ($action === 'update') {
        $qty = max(1, min(10, (int) ($_POST['qty'] ?? 1)));
        $stmt = $conn->prepare('UPDATE cart SET quantity = ? WHERE user_id = ? AND food_id = ?');
        $stmt->bind_param('iii', $qty, $userId, $foodId);
        $stmt->execute();
        $stmt->close();
        flash('success', 'Cart updated.');
    }

    if ($action === 'remove') {
        $stmt = $conn->prepare('DELETE FROM cart WHERE user_id = ? AND food_id = ?');
        $stmt->bind_param('ii', $userId, $foodId);
        $stmt->execute();
        $stmt->close();
        flash('success', 'Item removed from cart.');
    }

    redirect('cart.php');
}

$summary = cart_summary($conn, $userId);

render_header('Your Cart');
?>
<section class="section-heading">
    <div>
        <p class="eyebrow">Cart</p>
        <h1>Review your order before checkout</h1>
    </div>
</section>

<?php if (empty($summary['items'])): ?>
    <section class="empty-state">
        <h2>Your cart is empty</h2>
        <p>Add a few dishes and come back when you are ready to place the order.</p>
        <a class="button primary" href="menu.php">Explore Menu</a>
    </section>
<?php else: ?>
    <section class="cart-layout">
        <div class="cart-items">
            <?php foreach ($summary['items'] as $item): ?>
                <article class="cart-card">
                    <div>
                        <span class="tag"><?php echo h($item['category']); ?></span>
                        <h2><?php echo h($item['name']); ?></h2>
                        <p><?php echo h(format_price((float) $item['price'])); ?> each</p>
                    </div>
                    <form class="cart-form" method="post">
                        <input type="hidden" name="food_id" value="<?php echo (int) $item['food_id']; ?>">
                        <input type="hidden" name="action" value="update">
                        <input type="number" name="qty" min="1" max="10" value="<?php echo (int) $item['quantity']; ?>">
                        <button class="button secondary" type="submit">Update</button>
                    </form>
                    <div class="cart-line-total">
                        <?php echo h(format_price((float) $item['line_total'])); ?>
                    </div>
                    <form method="post">
                        <input type="hidden" name="food_id" value="<?php echo (int) $item['food_id']; ?>">
                        <input type="hidden" name="action" value="remove">
                        <button class="button ghost" type="submit">Remove</button>
                    </form>
                </article>
            <?php endforeach; ?>
        </div>

        <aside class="summary-card">
            <h2>Bill Summary</h2>
            <div class="summary-row"><span>Subtotal</span><strong><?php echo h(format_price((float) $summary['subtotal'])); ?></strong></div>
            <div class="summary-row"><span>Delivery Fee</span><strong><?php echo h(format_price((float) $summary['delivery_fee'])); ?></strong></div>
            <div class="summary-row"><span>Tax</span><strong><?php echo h(format_price((float) $summary['tax'])); ?></strong></div>
            <div class="summary-row total"><span>Total</span><strong><?php echo h(format_price((float) $summary['grand_total'])); ?></strong></div>
            <a class="button primary full-width" href="order.php">Proceed to Checkout</a>
        </aside>
    </section>
<?php endif; ?>
<?php render_footer(); ?>

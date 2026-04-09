<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

$statsQuery = $conn->query(
    'SELECT COUNT(*) AS total_orders,
            SUM(CASE WHEN status = "Delivered" THEN 1 ELSE 0 END) AS delivered_orders,
            SUM(CASE WHEN actual_delivery_minutes IS NOT NULL THEN 1 ELSE 0 END) AS ai_ready_orders,
            AVG(CASE WHEN actual_delivery_minutes IS NOT NULL THEN actual_delivery_minutes END) AS avg_delivery_minutes
     FROM orders'
);
$stats = $statsQuery->fetch_assoc();
$menuCount = $conn->query('SELECT COUNT(*) AS total_menu_items FROM food_items')->fetch_assoc();
$restaurantCount = $conn->query('SELECT COUNT(*) AS total_restaurants FROM restaurants')->fetch_assoc();
$isAdmin = is_logged_in() && ((current_user()['role'] ?? '') === 'admin');
$selectedCity = current_delivery_city();
$cityOptions = delivery_city_options();

render_header('Food Delivery Intelligence Platform');
?>
<section class="hero hero-expanded">
    <div class="hero-copy">
        <p class="eyebrow">Welcome in online Food Delivery Platform</p>
        <h1>Place your order in just minutes and enjoy fast, fresh delivery to your doorstep.</h1>
        <p class="hero-text">
            <?php echo h(app_name()); ?> is now a professional starter with a customer storefront, dispatch dashboard, historical order analytics, and preprocessed delivery features ready for modeling.
        </p>
        <form class="delivery-location-form" method="post" action="<?php echo h(app_path('index.php')); ?>">
            <label class="delivery-location-label" for="home-delivery-city">Delivery location</label>
            <div class="location-select-shell location-select-shell-hero">
                <span class="location-select-icon" aria-hidden="true"></span>
                <select name="delivery_city" id="home-delivery-city" class="location-select-input" onchange="this.form.submit()">
                    <?php foreach ($cityOptions as $city): ?>
                        <option value="<?php echo h($city); ?>" <?php echo $selectedCity === $city ? 'selected' : ''; ?>>
                            <?php echo h($city); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
        <div class="hero-actions">
            <a class="button primary" href="<?php echo h(app_path('menu.php')); ?>">Browse Menu</a>
            <?php if (is_logged_in()): ?>
                <a class="button secondary" href="<?php echo h(app_path('history.php')); ?>">View Order History</a>
            <?php else: ?>
                <a class="button secondary" href="<?php echo h(app_path('register.php')); ?>">Create Account</a>
            <?php endif; ?>
        </div>
    </div>
    <div class="hero-card">
        <div class="stat">
            <span class="stat-number"><?php echo (int) ($stats['delivered_orders'] ?? 0); ?></span>
            <span class="stat-label">historical deliveries tracked</span>
        </div>
        <div class="stat">
            <span class="stat-number"><?php echo (int) ($stats['ai_ready_orders'] ?? 0); ?></span>
            <span class="stat-label"> training rows seeded</span>
        </div>
        <div class="stat">
            <span class="stat-number"><?php echo (int) ($menuCount['total_menu_items'] ?? 0); ?></span>
            <span class="stat-label">menu items ready for ordering</span>
        </div>
    </div>
</section>

<section class="kpi-grid compact-grid">
    <article class="kpi-card">
        <span class="kpi-label">Average Delivered Time</span>
        <strong class="kpi-number"><?php echo (int) round((float) ($stats['avg_delivery_minutes'] ?? 0)); ?> min</strong>
    </article>
    <article class="kpi-card">
        <span class="kpi-label">Admin Monitoring</span>
        <strong class="kpi-number">Live</strong>
    </article>
    <article class="kpi-card">
        <span class="kpi-label">Restaurant Partners</span>
        <strong class="kpi-number"><?php echo (int) ($restaurantCount['total_restaurants'] ?? 0); ?></strong>
    </article>
    <article class="kpi-card">
        <span class="kpi-label">Customer Experience</span>
        <strong class="kpi-number">Tracked</strong>
    </article>
</section>

<section class="feature-grid">
    <article class="feature-card">
        <h2>Professional Ordering Flow</h2>
        <p>Customers can register, place orders, choose delivery zones, and review their personal order history with timing and rating details.</p>
    </article>
    <article class="feature-card">
        <h2>Dispatch and Delivery Operations</h2>
        <p>Admins get delivery metrics, status controls, ETA visibility, and a dashboard designed around active operations instead of raw tables only.</p>
    </article>
    <article class="feature-card">
        <h2>Preprocessing Built In</h2>
        <p>Historical order, basket, timing, zone, traffic, weather, and customer repeat behavior are preprocessed into model-ready features.</p>
    </article>
</section>

<section class="analytics-grid">
    <article class="info-card slim-card">
        <p class="eyebrow">AI Workflow</p>
        <h2>From delivery history to model-ready dataset</h2>
        <p>The admin center preprocesses order hour, weekend flag, repeat-customer history, basket size, prep time, distance, traffic, weather, and delay labels for downstream modeling.</p>
        <div class="hero-actions">
            <?php if ($isAdmin): ?>
                <a class="button secondary" href="<?php echo h(app_path('admin/ai-modeling.php')); ?>">Open  Modeling Center</a>
            <?php elseif (is_logged_in()): ?>
                <a class="button secondary" href="<?php echo h(app_path('history.php')); ?>">Review Your Order History</a>
            <?php else: ?>
                <a class="button secondary" href="<?php echo h(app_path('login.php')); ?>">Log In to Explore the Platform</a>
            <?php endif; ?>
        </div>
    </article>
    <article class="info-card slim-card">
        <p class="eyebrow">Use Cases</p>
        <h2>Train demand, ETA, and service-quality models</h2>
        <p>Use the exported dataset to forecast delivery delay, classify on-time probability, rank delivery zones by risk, or estimate customer service outcomes.</p>
    </article>
</section>
<?php render_footer(); ?>

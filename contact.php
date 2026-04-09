<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

render_header('Contact');
?>
<section class="info-page-shell">
    <article class="info-card">
        <p class="eyebrow">Contact</p>
        <h1>Reach the QuickBite support team</h1>
        <div class="contact-grid">
            <div class="contact-item">
                <h2>Email</h2>
                <p>manjeetkumarclg555@gmail.com</p>
            </div>
            <div class="contact-item">
                <h2>Phone</h2>
                <p>+91 7070294101</p>
            </div>
            <div class="contact-item">
                <h2>Address</h2>
                <p>Food Street, Shahpur, Hyderabad, India</p>
            </div>
            <div class="contact-item">
                <h2>Working Hours</h2>
                <p>Daily from 10:00 AM to 11:00 PM</p>
            </div>
        </div>
    </article>
</section>
<?php render_footer(); ?>

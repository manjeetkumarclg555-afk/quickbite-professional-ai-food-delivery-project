<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

if (is_logged_in()) {
    redirect('index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare('SELECT id, name, phone, profile_photo, email, password, role FROM users WHERE email = ? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    $seededAdminMatch = $user
        && $user['role'] === 'admin'
        && $user['password'] === 'ADMIN123_SEEDED'
        && $password === 'admin123';

    $seededDemoUserMatch = $user
        && $user['role'] === 'customer'
        && $user['password'] === 'SEEDED_USER'
        && $password === 'demo123';

    if ($user && ($seededAdminMatch || $seededDemoUserMatch || password_verify($password, $user['password']))) {
        unset($user['password']);
        $_SESSION['user'] = $user;
        flash('success', 'Welcome back, ' . $user['name'] . '!');
        redirect('index.php');
    }

    flash('error', 'Invalid email or password.');
    redirect('login.php');
}

render_header('Login');
?>
<section class="auth-shell">
    <form class="auth-card login-form" method="post">
        <p class="eyebrow">Welcome to My online testy food System</p>
        <h1>Log in to continue</h1>
        <label>
            <span>Email</span>
            <input type="email" name="email" placeholder="you@example.com" required>
        </label>
        <label>
            <span>Password</span>
            <div class="password-field" data-password-field>
                <input type="password" name="password" placeholder="Enter your password" required data-password-input>
                <button class="password-toggle" type="button" aria-label="Show password" aria-pressed="false" data-password-toggle>
                    <span class="password-toggle-show" aria-hidden="true">
                        <svg viewBox="0 0 24 24" role="presentation" focusable="false">
                            <path d="M12 5C6.5 5 2.1 8.4 1 12c1.1 3.6 5.5 7 11 7s9.9-3.4 11-7c-1.1-3.6-5.5-7-11-7Zm0 11.2A4.2 4.2 0 1 1 12 7.8a4.2 4.2 0 0 1 0 8.4Zm0-6.4a2.2 2.2 0 1 0 0 4.4 2.2 2.2 0 0 0 0-4.4Z"/>
                        </svg>
                    </span>
                    <span class="password-toggle-hide" aria-hidden="true" hidden>
                        <svg viewBox="0 0 24 24" role="presentation" focusable="false">
                            <path d="m3.3 2 18.7 18.7-1.4 1.4-3.2-3.2A12.8 12.8 0 0 1 12 20C6.5 20 2.1 16.6 1 13c.6-1.8 1.9-3.5 3.8-4.8L1.9 3.4 3.3 2Zm7 7 3 3a2.1 2.1 0 0 0-3-3Zm9.7 4c-.6 1.8-1.9 3.5-3.8 4.8l-1.5-1.5A10.3 10.3 0 0 0 19.8 13c-1.1-3-4.9-6-9.8-6-.8 0-1.6.1-2.4.3L5.9 5.6A13 13 0 0 1 12 4c5.5 0 9.9 3.4 11 7ZM12 8.8c1.8 0 3.2 1.4 3.2 3.2 0 .5-.1 1-.3 1.4l-4.3-4.3c.4-.2.9-.3 1.4-.3Zm-4.2.9 4.5 4.5c-.1 0-.2 0-.3 0A3.2 3.2 0 0 1 8.8 11c0-.5.1-.9.3-1.3Z"/>
                        </svg>
                    </span>
                </button>
            </div>
        </label>
        <button  class="button primary" type="submit">Login</button>
        <!-- <p class="muted">Admin demo: <strong>admin@quickbite.test</strong> / <strong>admin123</strong></p> -->
        <!-- <p class="muted">Customer demo: <strong>aarav@quickbite.test</strong> / <strong>demo123</strong></p> -->
    </form>
</section>
<script>
(() => {
    document.querySelectorAll('[data-password-field]').forEach((field) => {
        const input = field.querySelector('[data-password-input]');
        const toggle = field.querySelector('[data-password-toggle]');
        const showIcon = field.querySelector('.password-toggle-show');
        const hideIcon = field.querySelector('.password-toggle-hide');

        if (!input || !toggle || !showIcon || !hideIcon) {
            return;
        }

        toggle.addEventListener('click', () => {
            const showingPassword = input.type === 'text';
            input.type = showingPassword ? 'password' : 'text';
            toggle.setAttribute('aria-label', showingPassword ? 'Show password' : 'Hide password');
            toggle.setAttribute('aria-pressed', showingPassword ? 'false' : 'true');
            showIcon.hidden = !showingPassword;
            hideIcon.hidden = showingPassword;
        });
    });
})();
</script>
<?php render_footer(); ?>

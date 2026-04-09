<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/contact_notifications.php';

const SIGNUP_OTP_TTL_SECONDS = 600;
const SIGNUP_OTP_MAX_ATTEMPTS = 5;

function register_pending_signup(): ?array
{
    $pending = $_SESSION['signup_otp'] ?? null;
    return is_array($pending) ? $pending : null;
}

function register_clear_pending_signup(): void
{
    unset($_SESSION['signup_otp']);
}

function register_normalize_phone(string $phone): string
{
    $phone = trim($phone);
    $hasPlus = str_starts_with($phone, '+');
    $digits = preg_replace('/\D+/', '', $phone) ?? '';

    if ($digits === '') {
        return '';
    }

    if (!$hasPlus && strlen($digits) === 10) {
        return '+91' . $digits;
    }

    if (!$hasPlus && str_starts_with($digits, '91') && strlen($digits) === 12) {
        return '+' . $digits;
    }

    return ($hasPlus ? '+' : '') . $digits;
}

function register_mask_phone(string $phone): string
{
    $digits = preg_replace('/\D+/', '', $phone) ?? '';
    if (strlen($digits) <= 4) {
        return $phone;
    }

    return str_repeat('*', max(0, strlen($digits) - 4)) . substr($digits, -4);
}

function register_generate_otp(): string
{
    return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function register_phone_is_valid(string $phone): bool
{
    $digits = preg_replace('/\D+/', '', $phone) ?? '';
    return strlen($digits) >= 10 && strlen($digits) <= 15;
}

function register_store_pending_signup(array $form, string $passwordHash, string $otp): void
{
    $_SESSION['signup_otp'] = [
        'name' => $form['name'],
        'phone' => $form['phone'],
        'email' => $form['email'],
        'password_hash' => $passwordHash,
        'otp_hash' => $otp !== '' ? hash('sha256', $otp) : '',
        'expires_at' => time() + SIGNUP_OTP_TTL_SECONDS,
        'attempts' => 0,
        'otp_mode' => sms_gateway_manages_otp() ? 'provider' : 'local',
    ];
}

function register_send_otp_message(string $name, string $email, string $phone, string $otp): string
{
    $delivery = send_signup_otp_notification($name, $email, $phone, $otp);
    return $delivery['sms_sent']
        ? 'OTP sent to your mobile number. Enter it below to complete signup.'
        : '';
}

function register_provider_managed_otp(array $pendingSignup): bool
{
    return (($pendingSignup['otp_mode'] ?? '') === 'provider') && sms_gateway_manages_otp();
}

if (is_logged_in()) {
    redirect('index.php');
}

$pendingSignup = register_pending_signup();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'send_otp';

    if ($action === 'edit_details') {
        register_clear_pending_signup();
        flash('success', 'You can update your details and request a fresh OTP.');
        redirect('register.php');
    }

    if ($action === 'send_otp') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $phone = register_normalize_phone((string) ($_POST['phone'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');

        if ($name === '' || !register_phone_is_valid($phone) || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 6) {
            flash('error', 'Please provide a valid name, phone number, email, and password with at least 6 characters.');
            redirect('register.php');
        }

        $checkStmt = $conn->prepare('SELECT id FROM users WHERE email = ? OR phone = ? LIMIT 1');
        $checkStmt->bind_param('ss', $email, $phone);
        $checkStmt->execute();
        $exists = $checkStmt->get_result()->fetch_assoc();
        $checkStmt->close();

        if ($exists) {
            flash('error', 'That email or phone number is already registered.');
            redirect('register.php');
        }

        if (!sms_gateway_ready()) {
            flash('error', sms_gateway_label() . ' is not configured. Add provider credentials in config/notifications.php or environment variables before allowing signup.');
            redirect('register.php');
        }

        $otp = sms_gateway_manages_otp() ? '' : register_generate_otp();
        register_store_pending_signup(
            [
                'name' => $name,
                'phone' => $phone,
                'email' => $email,
            ],
            password_hash($password, PASSWORD_DEFAULT),
            $otp
        );

        $otpMessage = register_send_otp_message($name, $email, $phone, $otp);
        if ($otpMessage === '') {
            register_clear_pending_signup();
            $providerError = notification_last_sms_error();
            flash(
                'error',
                $providerError !== ''
                    ? 'OTP could not be delivered: ' . $providerError
                    : 'OTP could not be delivered to the mobile number. Please verify the SMS provider credentials and try again.'
            );
            redirect('register.php');
        }

        flash('success', $otpMessage);
        redirect('register.php');
    }

    $pendingSignup = register_pending_signup();
    if (!$pendingSignup) {
        flash('error', 'Your verification session expired. Please sign up again.');
        redirect('register.php');
    }

    if ($action === 'resend_otp') {
        if (!sms_gateway_ready()) {
            register_clear_pending_signup();
            flash('error', sms_gateway_label() . ' is not configured anymore. Please configure it and restart signup.');
            redirect('register.php');
        }

        $otp = sms_gateway_manages_otp() ? '' : register_generate_otp();
        register_store_pending_signup(
            [
                'name' => (string) $pendingSignup['name'],
                'phone' => (string) $pendingSignup['phone'],
                'email' => (string) $pendingSignup['email'],
            ],
            (string) $pendingSignup['password_hash'],
            $otp
        );

        $otpMessage = register_send_otp_message(
            (string) $pendingSignup['name'],
            (string) $pendingSignup['email'],
            (string) $pendingSignup['phone'],
            $otp
        );

        if ($otpMessage === '') {
            register_clear_pending_signup();
            $providerError = notification_last_sms_error();
            flash(
                'error',
                $providerError !== ''
                    ? 'OTP resend failed: ' . $providerError
                    : 'OTP resend failed because the SMS gateway did not accept the request. Please configure the provider and restart signup.'
            );
            redirect('register.php');
        }

        flash('success', $otpMessage);
        redirect('register.php');
    }

    if ($action === 'verify_otp') {
        $otp = trim((string) ($_POST['otp'] ?? ''));
        if (!preg_match('/^\d{6}$/', $otp)) {
            flash('error', 'Please enter the 6-digit OTP sent to your mobile number.');
            redirect('register.php');
        }

        if ((int) ($pendingSignup['expires_at'] ?? 0) < time()) {
            register_clear_pending_signup();
            flash('error', 'OTP expired. Please sign up again and request a fresh OTP.');
            redirect('register.php');
        }

        $otpValid = register_provider_managed_otp($pendingSignup)
            ? check_sms_verification_code((string) $pendingSignup['phone'], $otp)
            : hash_equals((string) $pendingSignup['otp_hash'], hash('sha256', $otp));

        if (!$otpValid) {
            $_SESSION['signup_otp']['attempts'] = ((int) ($_SESSION['signup_otp']['attempts'] ?? 0)) + 1;
            if ((int) $_SESSION['signup_otp']['attempts'] >= SIGNUP_OTP_MAX_ATTEMPTS) {
                register_clear_pending_signup();
                flash('error', 'Too many incorrect OTP attempts. Please restart signup.');
            } else {
                $providerError = notification_last_sms_error();
                flash('error', $providerError !== '' ? 'OTP verification failed: ' . $providerError : 'Incorrect OTP. Please try again.');
            }
            redirect('register.php');
        }

        $checkStmt = $conn->prepare('SELECT id FROM users WHERE email = ? OR phone = ? LIMIT 1');
        $checkStmt->bind_param('ss', $pendingSignup['email'], $pendingSignup['phone']);
        $checkStmt->execute();
        $exists = $checkStmt->get_result()->fetch_assoc();
        $checkStmt->close();

        if ($exists) {
            register_clear_pending_signup();
            flash('error', 'That email or phone number is already registered.');
            redirect('register.php');
        }

        $role = 'customer';
        $stmt = $conn->prepare('INSERT INTO users(name, phone, email, password, role, phone_verified_at) VALUES(?, ?, ?, ?, ?, NOW())');
        $stmt->bind_param(
            'sssss',
            $pendingSignup['name'],
            $pendingSignup['phone'],
            $pendingSignup['email'],
            $pendingSignup['password_hash'],
            $role
        );
        $stmt->execute();
        $stmt->close();

        notify_signup_event((string) $pendingSignup['name'], (string) $pendingSignup['email'], (string) $pendingSignup['phone']);
        register_clear_pending_signup();

        flash('success', 'Mobile number verified and registration complete. Please log in.');
        redirect('login.php');
    }
}

$pendingSignup = register_pending_signup();

render_header('Create Account');
?>
<section class="auth-shell">
    <div class="auth-card">
        <p class="eyebrow">New customer</p>
        <h1>Create your account</h1>
        <?php if ($pendingSignup): ?>
            <div class="otp-panel">
                <p class="auth-note">We sent a 6-digit OTP to <strong><?php echo h(register_mask_phone((string) $pendingSignup['phone'])); ?></strong>. Verify it to finish your signup.</p>
                <div class="profile-info-list otp-meta">
                    <p><strong>Name:</strong> <?php echo h((string) $pendingSignup['name']); ?></p>
                    <p><strong>Phone:</strong> <?php echo h((string) $pendingSignup['phone']); ?></p>
                    <p><strong>Email:</strong> <?php echo h((string) $pendingSignup['email']); ?></p>
                </div>
                <form method="post" class="profile-password-form">
                    <input type="hidden" name="action" value="verify_otp">
                    <label>
                        <span>Enter OTP</span>
                        <input type="text" name="otp" inputmode="numeric" pattern="\d{6}" maxlength="6" placeholder="6-digit OTP" required>
                    </label>
                    <button class="button primary" type="submit">Verify OTP &amp; Create Account</button>
                </form>
                <div class="otp-actions">
                    <form method="post">
                        <input type="hidden" name="action" value="resend_otp">
                        <button class="button secondary" type="submit">Resend OTP</button>
                    </form>
                    <form method="post">
                        <input type="hidden" name="action" value="edit_details">
                        <button class="button ghost" type="submit">Edit Details</button>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <form method="post" class="profile-password-form">
                <input type="hidden" name="action" value="send_otp">
                <label>
                    <span>Full name</span>
                    <input type="text" name="name" placeholder="Priya Sharma" required>
                </label>
                <label>
                    <span>Phone Number</span>
                    <input type="text" name="phone" placeholder="+91 9876543210" required>
                </label>
                <label>
                    <span>Email</span>
                    <input type="email" name="email" placeholder="you@example.com" required>
                </label>
                <label>
                    <span>Password</span>
                    <div class="password-field" data-password-field>
                        <input type="password" name="password" placeholder="At least 6 characters" minlength="6" required data-password-input>
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
                <p class="auth-note">We will send a one-time password to your mobile number before creating the account.</p>
                <p class="muted">Already have an account? <a href="<?php echo h(app_path('login.php')); ?>">Log in here</a>.</p>
                <button class="button primary" type="submit">Send OTP</button>
            </form>
        <?php endif; ?>
    </div>
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

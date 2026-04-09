<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

require_login();

$user = current_user();
global $conn;
$avatarUrl = user_avatar_url($user);

// Order count
$orderStmt = $conn->prepare('SELECT COUNT(*) AS count FROM orders WHERE user_id = ?');
$orderStmt->bind_param('i', $user['id']);
$orderStmt->execute();
$orderCount = (int) $orderStmt->get_result()->fetch_assoc()['count'];
$orderStmt->close();

// Cart count
$cartCount = cart_count($conn, (int) $user['id']);

// Profile updates
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'change_password' && !empty($_POST['new_password'])) {
        if (strlen($_POST['new_password']) >= 6) {
            $hash = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
            $updateStmt = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
            $updateStmt->bind_param('si', $hash, $user['id']);
            if ($updateStmt->execute()) {
                flash('success', 'Password updated!');
            } else {
                flash('error', 'Update failed.');
            }
            $updateStmt->close();
            redirect('profile.php');
        } else {
            $message = 'Password too short.';
        }
    }

    if ($action === 'remove_photo') {
        $existingPhoto = trim((string) ($user['profile_photo'] ?? ''));
        if ($existingPhoto !== '') {
            $normalizedPhoto = str_replace('\\', '/', $existingPhoto);
            $fullPath = __DIR__ . '/' . ltrim($normalizedPhoto, '/');
            if (str_starts_with($normalizedPhoto, 'uploads/profile-photos/') && is_file($fullPath)) {
                @unlink($fullPath);
            }
        }

        $clearStmt = $conn->prepare('UPDATE users SET profile_photo = NULL WHERE id = ?');
        $clearStmt->bind_param('i', $user['id']);
        $clearStmt->execute();
        $clearStmt->close();
        refresh_current_user($conn);
        flash('success', 'Profile photo removed.');
        redirect('profile.php');
    }

    if ($action === 'upload_photo') {
        $upload = $_FILES['profile_photo'] ?? null;
        if (!$upload || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            $message = 'Please choose a profile picture first.';
        } elseif (($upload['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            $message = 'Photo upload failed. Please try again.';
        } elseif (($upload['size'] ?? 0) > 3 * 1024 * 1024) {
            $message = 'Profile picture must be under 3 MB.';
        } else {
            $imageInfo = @getimagesize($upload['tmp_name']);
            $extension = strtolower(pathinfo((string) $upload['name'], PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];

            if ($imageInfo === false || !in_array($extension, $allowedExtensions, true)) {
                $message = 'Please upload a valid JPG, PNG, or WebP image.';
            } else {
                $uploadDir = __DIR__ . '/uploads/profile-photos';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0775, true);
                }

                $newFileName = 'user-' . (int) $user['id'] . '-' . time() . '.' . $extension;
                $relativePath = 'uploads/profile-photos/' . $newFileName;
                $destination = $uploadDir . '/' . $newFileName;

                $saved = create_square_avatar((string) $upload['tmp_name'], $destination, $extension, 320);
                if (!$saved && !move_uploaded_file($upload['tmp_name'], $destination)) {
                    $message = 'Unable to save the profile picture right now.';
                } else {
                    $existingPhoto = trim((string) ($user['profile_photo'] ?? ''));
                    if ($existingPhoto !== '') {
                        $normalizedPhoto = str_replace('\\', '/', $existingPhoto);
                        $oldPath = __DIR__ . '/' . ltrim($normalizedPhoto, '/');
                        if (str_starts_with($normalizedPhoto, 'uploads/profile-photos/') && is_file($oldPath)) {
                            @unlink($oldPath);
                        }
                    }

                    $photoStmt = $conn->prepare('UPDATE users SET profile_photo = ? WHERE id = ?');
                    $photoStmt->bind_param('si', $relativePath, $user['id']);
                    $photoStmt->execute();
                    $photoStmt->close();
                    refresh_current_user($conn);
                    flash('success', 'Profile picture updated.');
                    redirect('profile.php');
                }
            }
        }
    }
}

$user = current_user();
$avatarUrl = user_avatar_url($user);
if ($message !== '') {
    flash('error', $message);
    redirect('profile.php');
}

render_header('My Profile');
?>
<section class="page-section">
    <div class="container">
        <div class="profile-page-heading">
            <p class="eyebrow">Customer Profile</p>
            <h1>My Profile</h1>
        </div>
        <div class="profile-card profile-hero-card">
            <div class="profile-avatar-block">
                <?php if ($avatarUrl): ?>
                    <img class="profile-avatar-image" src="<?php echo h($avatarUrl); ?>" alt="<?php echo h($user['name']); ?>" width="180" height="180">
                <?php else: ?>
                    <span class="profile-avatar-fallback"><?php echo strtoupper(substr((string) $user['name'], 0, 1)); ?></span>
                <?php endif; ?>
            </div>
            <div class="profile-avatar-content">
                <h2><?php echo h($user['name']); ?></h2>
                <p>Add your own profile picture so the account icon looks like Google-style avatar instead of just the initial.</p>
                <form method="POST" enctype="multipart/form-data" class="profile-photo-form">
                    <input type="hidden" name="action" value="upload_photo">
                    <label class="profile-file-label">
                        <span>Choose picture</span>
                        <input type="file" name="profile_photo" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" required>
                    </label>
                    <button type="submit" class="button primary">Upload Photo</button>
                </form>
                <?php if (!empty($user['profile_photo'])): ?>
                    <form method="POST" class="profile-remove-form">
                        <input type="hidden" name="action" value="remove_photo">
                        <button type="submit" class="button secondary">Remove Photo</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <div class="profile-grid">
            <div class="profile-card">
                <h2>Account Info</h2>
                <div class="profile-info-list">
                    <p><strong>Name:</strong> <?php echo h($user['name']); ?></p>
                    <p><strong>Phone:</strong> <?php echo h((string) ($user['phone'] ?? 'Not added')); ?></p>
                    <p><strong>Email:</strong> <?php echo h($user['email']); ?></p>
                    <p><strong>Total Orders:</strong> <?php echo $orderCount; ?></p>
                    <p><strong>Cart Items:</strong> <?php echo $cartCount; ?></p>
                </div>
            </div>
            <div class="profile-card">
                <h2>Change Password</h2>
                <form method="POST" class="profile-password-form">
                    <input type="hidden" name="action" value="change_password">
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="new_password" required minlength="6" class="form-input">
                    </div>
                    <button type="submit" class="button primary">Update Password</button>
                </form>
            </div>
        </div>
        <div class="profile-actions">
            <a href="<?php echo h(app_path('history.php')); ?>" class="button secondary">Order History</a>
            <a href="<?php echo h(app_path('cart.php')); ?>" class="button secondary">Cart</a>
        </div>
    </div>
</section>
<?php render_footer(); ?>


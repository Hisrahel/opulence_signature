<?php
$pageTitle  = 'Settings';
$activePage = 'settings';
require_once __DIR__ . '/_layout.php';
require_once APP_ROOT . '/config/mailer.php';

$success = $_GET['success'] ?? '';
$errMsg  = $_GET['error'] ?? '';

// ---------------------------------------------------------------
// Handle POST
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['_action'] ?? '';

    // ---- CHANGE PASSWORD ----
    if ($act === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password']     ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (!$current || !$new || !$confirm) {
            header('Location: settings.php?error=' . urlencode('Please fill in all password fields.') . '#password');
            exit;
        }
        if (strlen($new) < 8) {
            header('Location: settings.php?error=' . urlencode('New password must be at least 8 characters.') . '#password');
            exit;
        }
        if (!preg_match('/[A-Z]/', $new) || !preg_match('/[0-9]/', $new)) {
            header('Location: settings.php?error=' . urlencode('New password should include at least one uppercase letter and one number.') . '#password');
            exit;
        }
        if ($new !== $confirm) {
            header('Location: settings.php?error=' . urlencode('New password and confirmation do not match.') . '#password');
            exit;
        }
        if ($new === $current) {
            header('Location: settings.php?error=' . urlencode('New password must be different from your current password.') . '#password');
            exit;
        }

        try {
            $row = $pdo->prepare("SELECT * FROM admin_users WHERE id=?");
            $row->execute([$admin['id']]);
            $me = $row->fetch();

            if (!$me || !password_verify($current, $me['password'])) {
                header('Location: settings.php?error=' . urlencode('Current password is incorrect.') . '#password');
                exit;
            }

            $newHash = password_hash($new, PASSWORD_BCRYPT, ['cost' => 12]);
            $pdo->prepare("UPDATE admin_users SET password=? WHERE id=?")->execute([$newHash, $admin['id']]);

            // ---- SECURITY EMAIL NOTIFICATION ----
            // Sends a confirmation to the admin's registered email,
            // including the new password itself per the site owner's
            // request. See config/mailer.php for the trade-offs of that.
            $emailSent = false;
            $mailError = '';
            if (!empty($me['email'])) {
                $emailSent = sendPasswordChangedEmail($me['email'], $me['full_name'], $me['username'], $new);
                $mailError = $GLOBALS['last_mail_error'] ?? '';
            }

            $msg = $emailSent
                ? 'Password updated successfully.'
                : (!empty($me['email'])
                    ? 'Password updated. ' . ($mailError ? ': ' . $mailError : '.')
                    : 'Password updated.');

            header('Location: settings.php?success=' . urlencode($msg) . '#password');
        } catch (Exception $e) {
            header('Location: settings.php?error=' . urlencode('Could not update password. Please try again.') . '#password');
        }
        exit;
    }

    // ---- UPDATE PROFILE (name + email) ----
    if ($act === 'update_profile') {
        $name  = clean($_POST['name']  ?? '');
        $email = trim($_POST['email'] ?? '');

        if (!$name) {
            header('Location: settings.php?error=' . urlencode('Name is required.') . '#profile');
            exit;
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            header('Location: settings.php?error=' . urlencode('Please enter a valid email address.') . '#profile');
            exit;
        }

        try {
            if ($email === '') {
                // Field left blank: keep whatever email is already on file
                // rather than silently wiping it (e.g. if the form field
                // was ever removed/hidden by a template edit).
                $pdo->prepare("UPDATE admin_users SET full_name=? WHERE id=?")
                    ->execute([$name, $admin['id']]);
            } else {
                $pdo->prepare("UPDATE admin_users SET full_name=?, email=? WHERE id=?")
                    ->execute([$name, $email, $admin['id']]);
            }
            $_SESSION['admin_name'] = $name;
            header('Location: settings.php?success=' . urlencode('Profile updated.') . '#profile');
        } catch (Exception $e) {
            header('Location: settings.php?error=' . urlencode('Could not update profile.') . '#profile');
        }
        exit;
    }

    // ---- UPDATE THEME (dark / light) ----
    if ($act === 'update_theme') {
        $theme = $_POST['theme'] ?? 'dark';
        $theme = in_array($theme, ['dark', 'light'], true) ? $theme : 'dark';
        try {
            setSetting($pdo, 'admin_theme', $theme);
            header('Location: settings.php?success=' . urlencode('Theme updated.') . '#appearance');
        } catch (Exception $e) {
            header('Location: settings.php?error=' . urlencode('Could not update theme.') . '#appearance');
        }
        exit;
    }

    // ---- UPDATE GENERAL SITE SETTINGS ----
    if ($act === 'update_general') {
        try {
            setSetting($pdo, 'contact_email',           clean($_POST['contact_email'] ?? ''));
            setSetting($pdo, 'contact_phone',            clean($_POST['contact_phone'] ?? ''));
            setSetting($pdo, 'whatsapp_number',          clean($_POST['whatsapp_number'] ?? ''));
            setSetting($pdo, 'free_delivery_threshold',  (string)(float)($_POST['free_delivery_threshold'] ?? 0));
            setSetting($pdo, 'maintenance_mode',         isset($_POST['maintenance_mode']) ? '1' : '0');
            header('Location: settings.php?success=' . urlencode('Site settings saved.') . '#general');
        } catch (Exception $e) {
            header('Location: settings.php?error=' . urlencode('Could not save settings.') . '#general');
        }
        exit;
    }
}

// ---------------------------------------------------------------
// Load current values
// ---------------------------------------------------------------
$currentTheme    = getSetting($pdo, 'admin_theme', 'dark');
$contactEmail    = getSetting($pdo, 'contact_email', '');
$contactPhone    = getSetting($pdo, 'contact_phone', '');
$whatsappNumber  = getSetting($pdo, 'whatsapp_number', '');
$deliveryThresh  = getSetting($pdo, 'free_delivery_threshold', '50000');
$maintenanceMode = getSetting($pdo, 'maintenance_mode', '0');

// Admin's own email (for password-change notifications)
$meRow = $pdo->prepare("SELECT email FROM admin_users WHERE id=?");
$meRow->execute([$admin['id']]);
$adminEmail = $meRow->fetchColumn() ?: '';

// Is PHPMailer installed? Shown as a status hint so the admin knows
// whether password-change emails will actually send.
$mailerReady = is_file(APP_ROOT . '/vendor/autoload.php');
?>

<?php if ($success): ?>
    <div style="background:#142a18;border:1px solid #2a5a32;color:#8af0a0;border-radius:8px;padding:12px 16px;margin-bottom:20px;font-size:14px">
        <i class="fa-solid fa-circle-check me-2"></i><?= htmlspecialchars($success) ?>
    </div>
<?php elseif ($errMsg): ?>
    <div style="background:#2a1515;border:1px solid #5a2a2a;color:#ff8a8a;border-radius:8px;padding:12px 16px;margin-bottom:20px;font-size:14px">
        <i class="fa-solid fa-circle-exclamation me-2"></i><?= htmlspecialchars($errMsg) ?>
    </div>
<?php endif; ?>

<?php if (!$mailerReady): ?>
    <div style="background:#2a2310;border:1px solid #5a4a1a;color:#f0d98a;border-radius:8px;padding:12px 16px;margin-bottom:20px;font-size:13px">
        <i class="fa-solid fa-triangle-exclamation me-2"></i>
        Email notifications are not active yet. Run <code style="background:rgba(0,0,0,.25);padding:1px 6px;border-radius:4px">composer require phpmailer/phpmailer</code>
        in the project root and set your SMTP credentials in <code style="background:rgba(0,0,0,.25);padding:1px 6px;border-radius:4px">config/mailer.php</code>.
    </div>
<?php endif; ?>

<div class="row g-3">

    <!-- ===== PROFILE ===== -->
    <div class="col-12 col-lg-6" id="profile">
        <div class="form-card">
            <h5 style="color:var(--text);margin-bottom:4px"><i class="fa-solid fa-user me-2" style="color:var(--gold)"></i>Profile</h5>
            <div class="section-sub" style="margin-bottom:18px">Your admin account details</div>
            <form method="POST">
                <input type="hidden" name="_action" value="update_profile">
                <div class="mb-3">
                    <label class="form-label">Display Name</label>
                    <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($admin['name'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Role</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars(ucfirst($admin['role'] ?? '')) ?>" disabled style="opacity:.6">
                </div>
                <button type="submit" class="btn-gold"><i class="fa-solid fa-floppy-disk me-1"></i>Save Profile</button>
            </form>
        </div>
    </div>

    <!-- ===== APPEARANCE ===== -->
    <div class="col-12 col-lg-6" id="appearance">
        <div class="form-card">
            <h5 style="color:var(--text);margin-bottom:4px"><i class="fa-solid fa-palette me-2" style="color:var(--gold)"></i>Appearance</h5>
            <div class="section-sub" style="margin-bottom:18px">Choose how the admin panel looks</div>
            <form method="POST" id="themeForm">
                <input type="hidden" name="_action" value="update_theme">
                <div style="display:flex;gap:12px">
                    <label style="flex:1;cursor:pointer">
                        <input type="radio" name="theme" value="dark" <?= $currentTheme === 'dark' ? 'checked' : '' ?> style="display:none" class="theme-radio" onchange="this.form.submit()">
                        <div style="border:2px solid <?= $currentTheme === 'dark' ? 'var(--gold)' : 'var(--border)' ?>;border-radius:12px;padding:16px;text-align:center;background:var(--input-bg)">
                            <div style="width:100%;height:48px;border-radius:8px;background:linear-gradient(135deg,#161616,#0a0a0a);border:1px solid #2a2a2a;margin-bottom:10px;display:flex;align-items:center;justify-content:center">
                                <i class="fa-solid fa-moon" style="color:var(--gold)"></i>
                            </div>
                            <span style="font-size:13px;color:<?= $currentTheme === 'dark' ? 'var(--gold)' : 'var(--muted)' ?>;font-weight:600">Dark Mode</span>
                        </div>
                    </label>
                    <label style="flex:1;cursor:pointer">
                        <input type="radio" name="theme" value="light" <?= $currentTheme === 'light' ? 'checked' : '' ?> style="display:none" class="theme-radio" onchange="this.form.submit()">
                        <div style="border:2px solid <?= $currentTheme === 'light' ? 'var(--gold)' : 'var(--border)' ?>;border-radius:12px;padding:16px;text-align:center;background:var(--input-bg)">
                            <div style="width:100%;height:48px;border-radius:8px;background:linear-gradient(135deg,#f4f4f4,#dcdcdc);border:1px solid #ccc;margin-bottom:10px;display:flex;align-items:center;justify-content:center">
                                <i class="fa-solid fa-sun" style="color:#b8932e"></i>
                            </div>
                            <span style="font-size:13px;color:<?= $currentTheme === 'light' ? 'var(--gold)' : 'var(--muted)' ?>;font-weight:600">Light Mode</span>
                        </div>
                    </label>
                </div>
                <small style="color:var(--muted);font-size:12px;margin-top:10px;display:block">Applies to the admin panel only — selecting an option saves it automatically.</small>
            </form>
        </div>
    </div>

    <!-- ===== CHANGE PASSWORD ===== -->
    <div class="col-12 col-lg-6" id="password">
        <div class="form-card">
            <h5 style="color:var(--text);margin-bottom:4px"><i class="fa-solid fa-lock me-2" style="color:var(--gold)"></i>Change Password</h5>
            <div class="section-sub" style="margin-bottom:18px">
                Use a strong password you don't use elsewhere.
            </div>
            <form method="POST" id="passwordForm">
                <input type="hidden" name="_action" value="change_password">
                <div class="mb-3">
                    <label class="form-label">Current Password</label>
                    <input type="password" name="current_password" class="form-control" required autocomplete="current-password">
                </div>
                <div class="mb-3">
                    <label class="form-label">New Password</label>
                    <input type="password" name="new_password" id="newPassword" class="form-control" required minlength="8" autocomplete="new-password">
                    <small style="color:var(--muted);font-size:12px;margin-top:4px;display:block">At least 8 characters, including 1 uppercase letter and 1 number</small>
                </div>
                <div class="mb-3">
                    <label class="form-label">Confirm New Password</label>
                    <input type="password" name="confirm_password" id="confirmPassword" class="form-control" required minlength="8" autocomplete="new-password">
                    <small id="matchHint" style="font-size:12px;margin-top:4px;display:block"></small>
                </div>
                <button type="submit" class="btn-gold"><i class="fa-solid fa-key me-1"></i>Update Password</button>
            </form>
        </div>
    </div>

    <!-- ===== GENERAL SITE SETTINGS ===== -->
    <div class="col-12 col-lg-6" id="general">
        <div class="form-card">
            <h5 style="color:var(--text);margin-bottom:4px"><i class="fa-solid fa-sliders me-2" style="color:var(--gold)"></i>General Site Settings</h5>
            <div class="section-sub" style="margin-bottom:18px">Contact info and store-wide defaults</div>
            <form method="POST">
                <input type="hidden" name="_action" value="update_general">
                <div class="mb-3">
                    <label class="form-label">Contact Email</label>
                    <input type="email" name="contact_email" class="form-control" value="<?= htmlspecialchars($contactEmail) ?>" placeholder="opulencesignature001@gmail.com">
                </div>
                <div class="mb-3">
                    <label class="form-label">Contact Phone</label>
                    <input type="text" name="contact_phone" class="form-control" value="<?= htmlspecialchars($contactPhone) ?>" placeholder="+234 810 424 0201">
                </div>
                <div class="mb-3">
                    <label class="form-label">WhatsApp Number <small style="color:var(--muted)">(digits only, with country code)</small></label>
                    <input type="text" name="whatsapp_number" class="form-control" value="<?= htmlspecialchars($whatsappNumber) ?>" placeholder="2348104240201">
                </div>
                <!-- <div class="mb-3">
                    <label class="form-label">Free Delivery Threshold (₦)</label>
                    <input type="number" name="free_delivery_threshold" class="form-control" min="0" step="500" value="<?= htmlspecialchars($deliveryThresh) ?>">
                </div> -->
                <div class="mb-3 form-check" style="display:flex;align-items:center;gap:10px">
                    <input type="checkbox" name="maintenance_mode" id="maintMode" class="form-check-input" style="width:18px;height:18px;border-radius:4px" <?= $maintenanceMode === '1' ? 'checked' : '' ?>>
                    <label for="maintMode" class="form-label mb-0">Maintenance Mode</label>
                </div>
                <small style="color:var(--muted);font-size:12px;margin-bottom:14px;display:block">Note: these values are stored for reference. Wiring them into the storefront (e.g. WhatsApp link, delivery threshold) requires the front-end pages to read them from this table.</small>
                <button type="submit" class="btn-gold"><i class="fa-solid fa-floppy-disk me-1"></i>Save Settings</button>
            </form>
        </div>
    </div>

</div>

<script>
    // Live "passwords match" hint — purely cosmetic, server still validates.
    (function() {
        const newPw = document.getElementById('newPassword');
        const confirmPw = document.getElementById('confirmPassword');
        const hint = document.getElementById('matchHint');
        if (!newPw || !confirmPw || !hint) return;

        function check() {
            if (!confirmPw.value) {
                hint.textContent = '';
                return;
            }
            if (newPw.value === confirmPw.value) {
                hint.textContent = 'Passwords match';
                hint.style.color = '#28a745';
            } else {
                hint.textContent = 'Passwords do not match';
                hint.style.color = '#dc3545';
            }
        }
        newPw.addEventListener('input', check);
        confirmPw.addEventListener('input', check);
    })();
</script>

<?php require_once __DIR__ . '/_footer.php'; ?>
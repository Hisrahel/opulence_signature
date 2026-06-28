<?php
// admin/reset-password.php — Step 2 of password recovery: consume the
// token from the emailed link and set a new password.

session_start();
if (isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/mailer.php';

$pdo = getDB();

$rawToken = $_GET['token'] ?? $_POST['token'] ?? '';
$error    = '';
$done     = false;
$tokenRow = null;

/**
 * Looks up a (still valid, unused, unexpired) reset token by its hash.
 * Returns the joined admin_users + password_resets row, or null.
 */
function findValidReset(PDO $pdo, string $rawToken): ?array
{
    if (!$rawToken) return null;
    $hash = hash('sha256', $rawToken);

    $stmt = $pdo->prepare(
        "SELECT pr.id AS reset_id, pr.expires_at, pr.used_at,
                au.id AS admin_id, au.full_name, au.username, au.email
         FROM password_resets pr
         JOIN admin_users au ON au.id = pr.admin_id
         WHERE pr.token_hash = ?
         LIMIT 1"
    );
    $stmt->execute([$hash]);
    $row = $stmt->fetch();
    return $row ?: null;
}

$tokenRow = findValidReset($pdo, $rawToken);

if (!$tokenRow) {
    $error = 'This reset link is invalid. Please request a new one.';
} elseif ($tokenRow['used_at']) {
    $error = 'This reset link has already been used. Please request a new one.';
} elseif (strtotime($tokenRow['expires_at']) < time()) {
    $error = 'This reset link has expired. Please request a new one.';
}

// ---------------------------------------------------------------
// Handle POST — set the new password
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    $new     = $_POST['new_password']     ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!$new || !$confirm) {
        $error = 'Please fill in both password fields.';
    } elseif (strlen($new) < 8) {
        $error = 'New password must be at least 8 characters.';
    } elseif (!preg_match('/[A-Z]/', $new) || !preg_match('/[0-9]/', $new)) {
        $error = 'New password should include at least one uppercase letter and one number.';
    } elseif ($new !== $confirm) {
        $error = 'New password and confirmation do not match.';
    } else {
        try {
            $pdo->beginTransaction();

            $newHash = password_hash($new, PASSWORD_BCRYPT, ['cost' => 12]);
            $pdo->prepare('UPDATE admin_users SET password = ? WHERE id = ?')
                ->execute([$newHash, $tokenRow['admin_id']]);

            // Mark this token used so the link can't be replayed.
            $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = ?')
                ->execute([$tokenRow['reset_id']]);

            // Also invalidate any other still-pending reset tokens for
            // this admin (e.g. if they requested the link twice).
            $pdo->prepare('DELETE FROM password_resets WHERE admin_id = ? AND used_at IS NULL')
                ->execute([$tokenRow['admin_id']]);

            $pdo->commit();

            // Send the same security notification used for in-panel
            // password changes, so there's one consistent audit trail
            // regardless of which flow was used to change the password.
            if (!empty($tokenRow['email'])) {
                sendPasswordChangedEmail($tokenRow['email'], $tokenRow['full_name'], $tokenRow['username'], $new);
            }

            $done = true;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Could not reset your password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset Password - Opulence Signature</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --gold: #C9A84C;
            --dark: #0a0a0a;
            --dark2: #111;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--dark);
            font-family: 'Segoe UI', sans-serif;
        }

        .login-card {
            background: #161616;
            border: 1px solid #2a2a2a;
            border-radius: 16px;
            padding: 48px 40px;
            width: 100%;
            max-width: 420px;
        }

        .login-logo {
            text-align: center;
            margin-bottom: 32px;
        }

        .login-logo span {
            font-size: 28px;
            font-weight: 800;
            color: #fff;
            letter-spacing: 4px;
        }

        .login-logo em {
            display: block;
            color: var(--gold);
            font-style: normal;
            font-size: 13px;
            letter-spacing: 6px;
            margin-top: 4px;
        }

        .login-card h2 {
            font-size: 20px;
            color: #fff;
            font-weight: 600;
            margin-bottom: 6px;
        }

        .login-card p.lead {
            color: #888;
            font-size: 14px;
            margin-bottom: 28px;
            line-height: 1.6;
        }

        .form-label {
            color: #bbb;
            font-size: 13px;
            margin-bottom: 6px;
        }

        .form-control {
            background: #1e1e1e;
            border: 1px solid #333;
            color: #fff;
            border-radius: 8px;
            padding: 12px 14px;
            font-size: 14px;
        }

        .form-control:focus {
            background: #1e1e1e;
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(201, 168, 76, .12);
            color: #fff;
        }

        .form-control::placeholder {
            color: #555;
        }

        .btn-login {
            background: var(--gold);
            color: #000;
            border: none;
            border-radius: 8px;
            padding: 13px;
            font-weight: 700;
            font-size: 15px;
            width: 100%;
            cursor: pointer;
            letter-spacing: .5px;
            transition: .2s;
        }

        .btn-login:hover {
            background: #b8932e;
        }

        .alert-danger {
            background: #2a1515;
            border: 1px solid #5a2a2a;
            color: #ff8a8a;
            border-radius: 8px;
            padding: 12px 14px;
            font-size: 13px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #142a18;
            border: 1px solid #2a5a32;
            color: #8af0a0;
            border-radius: 8px;
            padding: 14px 16px;
            font-size: 13.5px;
            line-height: 1.6;
            margin-bottom: 8px;
        }

        .input-icon {
            position: relative;
        }

        .input-icon i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #555;
            font-size: 13px;
        }

        .input-icon .form-control {
            padding-left: 38px;
        }

        .pass-wrap {
            position: relative;
        }

        .pass-wrap .toggle-pass {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #555;
            cursor: pointer;
            font-size: 13px;
            background: none;
            border: none;
        }

        .back-link {
            text-align: center;
            margin-top: 20px;
        }

        .back-link a {
            color: #666;
            font-size: 13px;
            text-decoration: none;
        }

        .back-link a:hover {
            color: var(--gold);
        }

        small.hint {
            color: #555;
            font-size: 12px;
            margin-top: 4px;
            display: block;
        }
    </style>
</head>

<body>
    <div class="login-card">
        <div class="login-logo">
            <span>OPULENCE</span>
            <em>ADMIN PANEL</em>
        </div>

        <?php if ($done): ?>
            <h2>Password Reset</h2>
            <div class="alert-success">
                <i class="fa-solid fa-circle-check me-2"></i>
                Your password has been updated successfully. You can now sign in with your new password.
            </div>
            <div class="back-link" style="margin-top:24px">
                <a href="login.php"><i class="fa-solid fa-arrow-right-to-bracket me-1"></i>Go to Sign In</a>
            </div>

        <?php elseif ($error && !$tokenRow): ?>
            <h2>Link Invalid</h2>
            <div class="alert-danger"><i class="fa-solid fa-circle-exclamation me-2"></i><?= htmlspecialchars($error) ?></div>
            <div class="back-link" style="margin-top:24px">
                <a href="forgot-password.php"><i class="fa-solid fa-rotate-left me-1"></i>Request a New Link</a>
            </div>

        <?php elseif ($tokenRow && ($tokenRow['used_at'] || strtotime($tokenRow['expires_at']) < time())): ?>
            <h2>Link Expired</h2>
            <div class="alert-danger"><i class="fa-solid fa-circle-exclamation me-2"></i><?= htmlspecialchars($error) ?></div>
            <div class="back-link" style="margin-top:24px">
                <a href="forgot-password.php"><i class="fa-solid fa-rotate-left me-1"></i>Request a New Link</a>
            </div>

        <?php else: ?>
            <h2>Set a New Password</h2>
            <p class="lead">Choose a strong new password for your admin account.</p>

            <?php if ($error): ?>
                <div class="alert-danger"><i class="fa-solid fa-circle-exclamation me-2"></i><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" autocomplete="off">
                <input type="hidden" name="token" value="<?= htmlspecialchars($rawToken) ?>">
                <div class="mb-3">
                    <label class="form-label">New Password</label>
                    <div class="input-icon pass-wrap">
                        <i class="fa-solid fa-lock"></i>
                        <input type="password" name="new_password" id="newPassword" class="form-control" placeholder="••••••••" required minlength="8">
                        <button type="button" class="toggle-pass" onclick="togglePass('newPassword','newIcon')">
                            <i class="fa-solid fa-eye" id="newIcon"></i>
                        </button>
                    </div>
                    <small class="hint">At least 8 characters, including 1 uppercase letter and 1 number</small>
                </div>
                <div class="mb-4">
                    <label class="form-label">Confirm New Password</label>
                    <div class="input-icon pass-wrap">
                        <i class="fa-solid fa-lock"></i>
                        <input type="password" name="confirm_password" id="confirmPassword" class="form-control" placeholder="••••••••" required minlength="8">
                        <button type="button" class="toggle-pass" onclick="togglePass('confirmPassword','confirmIcon')">
                            <i class="fa-solid fa-eye" id="confirmIcon"></i>
                        </button>
                    </div>
                    <small id="matchHint" class="hint"></small>
                </div>
                <button type="submit" class="btn-login">
                    <i class="fa-solid fa-key me-2"></i>Reset Password
                </button>
            </form>
            <div class="back-link">
                <a href="login.php"><i class="fa-solid fa-arrow-left me-1"></i>Back to Sign In</a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function togglePass(fieldId, iconId) {
            const f = document.getElementById(fieldId);
            const i = document.getElementById(iconId);
            f.type = f.type === 'password' ? 'text' : 'password';
            i.className = f.type === 'password' ? 'fa-solid fa-eye' : 'fa-solid fa-eye-slash';
        }

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
</body>

</html>
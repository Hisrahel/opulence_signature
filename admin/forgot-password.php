<?php
// admin/forgot-password.php
// Simple recovery: enter your email, receive a temporary password immediately.
// No reset links, no tokens, no expiry — just a working password in your inbox.

session_start();
if (isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/mailer.php';

$submitted = false;
$error     = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (!in_array(strtolower($email), [
        'olayemisrael5@gmail.com',
        'opulencesignature001@gmail.com',
    ], true)) {
        $error = 'That email is not authorised to recover this account.';
    } else {
        try {
            $pdo  = getDB();
            $stmt = $pdo->prepare('SELECT id, full_name, username FROM admin_users WHERE email = ? AND email != "" LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                $upper   = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
                $lower   = 'abcdefghjkmnpqrstuvwxyz';
                $digits  = '23456789';
                $symbols = '@#$!';

                $tempPassword  = $upper[random_int(0, strlen($upper) - 1)];
                $tempPassword .= $upper[random_int(0, strlen($upper) - 1)];
                $tempPassword .= $lower[random_int(0, strlen($lower) - 1)];
                $tempPassword .= $lower[random_int(0, strlen($lower) - 1)];
                $tempPassword .= $lower[random_int(0, strlen($lower) - 1)];
                $tempPassword .= $lower[random_int(0, strlen($lower) - 1)];
                $tempPassword .= $digits[random_int(0, strlen($digits) - 1)];
                $tempPassword .= $digits[random_int(0, strlen($digits) - 1)];
                $tempPassword .= $symbols[random_int(0, strlen($symbols) - 1)];

                // Shuffle so the pattern isn't predictable
                $tempPassword = str_shuffle($tempPassword);

                // Save the new hash
                $newHash = password_hash($tempPassword, PASSWORD_BCRYPT, ['cost' => 12]);
                $pdo->prepare('UPDATE admin_users SET password = ? WHERE id = ?')
                    ->execute([$newHash, $user['id']]);

                // Send the temporary password directly to their inbox
                $subject = 'Your Opulence Admin temporary password';
                $html = "
                    <div style='font-family:Arial,sans-serif;max-width:460px;margin:0 auto;
                                padding:28px;background:#faf8f3;border-radius:10px'>
                        <h2 style='color:#0d0d0d;margin:0 0 6px'>Temporary Password</h2>
                        <p style='color:#5a5a5a;font-size:14px;line-height:1.6;margin:0 0 20px'>
                            Hi " . htmlspecialchars($user['full_name']) . ", here is your temporary
                            password for the Opulence admin account
                            <strong>" . htmlspecialchars($user['username']) . "</strong>.
                        </p>
                        <div style='background:#f5e9c8;border-radius:8px;padding:16px 20px;
                                    text-align:center;margin-bottom:20px'>
                            <p style='margin:0 0 4px;font-size:12px;color:#888;letter-spacing:.08em;
                                       text-transform:uppercase'>Your temporary password</p>
                            <span style='font-family:monospace;font-size:22px;font-weight:700;
                                         color:#0d0d0d;letter-spacing:2px'>"
                    . htmlspecialchars($tempPassword) . "</span>
                        </div>
                        <p style='color:#5a5a5a;font-size:13px;line-height:1.6'>
                            Use this to sign in, then go to <strong>Settings → Change Password</strong>
                            to set a permanent password you'll remember.
                        </p>
                        <p style='color:#b42318;font-size:13px;background:#fdeceb;
                                   padding:12px;border-radius:8px;margin-top:16px;line-height:1.6'>
                            If you did not request this, your account may be at risk.
                            Contact your developer immediately.
                        </p>
                        <p style='color:#999;font-size:11px;margin-top:20px'>
                            Opulence Signature Admin Panel — automated security notice.
                        </p>
                    </div>
                ";

                if (!sendMail($email, $user['full_name'], $subject, $html)) {
                    error_log('Mail error: ' . ($GLOBALS['last_mail_error'] ?? 'unknown'));
                    $error = 'Mail failed: ' . ($GLOBALS['last_mail_error'] ?? 'unknown error');
                } else {
                    $submitted = true;
                }
            }
            if (!$error && !$submitted) {
                $submitted = true;
            }
        } catch (Exception $e) {
            $error = 'Something went wrong. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Forgot Password - Opulence Signature</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --gold: #C9A84C;
            --dark: #0a0a0a;
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

        h2 {
            font-size: 20px;
            color: #fff;
            font-weight: 600;
            margin-bottom: 6px;
        }

        p.lead {
            color: #888;
            font-size: 14px;
            margin-bottom: 28px;
            line-height: 1.6;
        }

        .form-label {
            color: #bbb;
            font-size: 13px;
            margin-bottom: 6px;
            display: block;
        }

        .form-control {
            width: 100%;
            background: #1e1e1e;
            border: 1px solid #333;
            color: #fff;
            border-radius: 8px;
            padding: 12px 14px 12px 38px;
            font-size: 14px;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(201, 168, 76, .12);
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
            margin-top: 4px;
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
            margin-bottom: 20px;
        }

        .input-icon i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #555;
            font-size: 13px;
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
    </style>
</head>

<body>
    <div class="login-card">
        <div class="login-logo">
            <span>OPULENCE</span>
            <em>ADMIN PANEL</em>
        </div>

        <?php if ($submitted): ?>
            <h2>Check Your Email</h2>
            <div class="alert-success">
                <i class="fa-solid fa-circle-check me-2"></i>
                Temporary password has been sent. Use it to sign in, then change your password in Settings.
            </div>
            <div class="back-link" style="margin-top:24px">
                <a href="login.php">
                    <i class="fa-solid fa-arrow-right-to-bracket me-1"></i>Go to Sign In
                </a>
            </div>

        <?php else: ?>
            <h2>Forgot Password?</h2>
            <p class="lead">
                Enter the email address on your admin account. We'll send a
                temporary password you can use to sign in straight away.
            </p>

            <?php if ($error): ?>
                <div class="alert-danger">
                    <i class="fa-solid fa-circle-exclamation me-2"></i><?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" autocomplete="off">
                <div>
                    <label class="form-label">Email Address</label>
                    <div class="input-icon">
                        <i class="fa-solid fa-envelope"></i>
                        <input type="email" name="email" class="form-control"
                            placeholder="you@example.com"
                            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                    </div>
                </div>
                <button type="submit" class="btn-login">
                    <i class="fa-solid fa-paper-plane me-2"></i>Send Temporary Password
                </button>
            </form>
            <div class="back-link">
                <a href="login.php">
                    <i class="fa-solid fa-arrow-left me-1"></i>Back to Sign In
                </a>
            </div>
        <?php endif; ?>
    </div>
</body>

</html>
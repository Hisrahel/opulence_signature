<?php
// admin/login.php

session_start();
if (isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/db.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    if ($username && $password) {
        if (adminLogin($username, $password)) {
            header('Location: index.php');
            exit;
        } else {
            $error = 'Invalid username or password.';
        }
    } else {
        $error = 'Please enter both username and password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Login - Opulence Signature</title>
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

        .login-card p {
            color: #888;
            font-size: 14px;
            margin-bottom: 28px;
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

        .forgot-link {
            text-align: right;
            margin-top: 8px;
        }

        .forgot-link a {
            color: #888;
            font-size: 12.5px;
            text-decoration: none;
            transition: color .2s;
        }

        .forgot-link a:hover {
            color: var(--gold);
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
        <h2>Welcome Back</h2>
        <p>Sign in to manage your store</p>

        <?php if ($error): ?>
            <div class="alert-danger"><i class="fa-solid fa-circle-exclamation me-2"></i><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <div class="mb-3">
                <label class="form-label">Username</label>
                <div class="input-icon">
                    <i class="fa-solid fa-user"></i>
                    <input type="text" name="username" class="form-control" placeholder="admin"
                        value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
                </div>
            </div>
            <div class="mb-4">
                <label class="form-label">Password</label>
                <div class="input-icon pass-wrap">
                    <i class="fa-solid fa-lock"></i>
                    <input type="password" name="password" id="passField" class="form-control"
                        placeholder="••••••••" required>
                    <button type="button" class="toggle-pass" onclick="togglePass()">
                        <i class="fa-solid fa-eye" id="passIcon"></i>
                    </button>
                </div>
                <div class="forgot-link">
                    <a href="forgot-password.php">Forgot password?</a>
                </div>
            </div>
            <button type="submit" class="btn-login">
                <i class="fa-solid fa-arrow-right-to-bracket me-2"></i>Sign In
            </button>
        </form>
        <div class="back-link">
            <a href="../index.php"><i class="fa-solid fa-arrow-left me-1"></i>Back to Site</a>
        </div>
    </div>
    <script>
        function togglePass() {
            const f = document.getElementById('passField');
            const i = document.getElementById('passIcon');
            f.type = f.type === 'password' ? 'text' : 'password';
            i.className = f.type === 'password' ? 'fa-solid fa-eye' : 'fa-solid fa-eye-slash';
        }
    </script>
</body>

</html>
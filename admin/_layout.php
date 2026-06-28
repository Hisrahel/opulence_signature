<?php
// admin/_layout.php - Shared admin header/sidebar (include at top of each admin page)
// Usage: include __DIR__ . '/_layout.php';
// Expects: $pageTitle (string), $activePage (string)

ob_start();

if (!defined('ADMIN_ROOT')) define('ADMIN_ROOT', __DIR__);
if (!defined('APP_ROOT'))   define('APP_ROOT', dirname(__DIR__));

require_once APP_ROOT . '/config/auth.php';
require_once APP_ROOT . '/config/db.php';
requireLogin();

$admin = currentAdmin();
$pageTitle = $pageTitle ?? 'Admin';
$activePage = $activePage ?? '';

// Count badges for sidebar
$pdo = getDB();
function countBadge(PDO $pdo, string $table, string $status = 'pending'): int
{
    try {
        $col = str_contains($table, 'booking') ? 'status' : 'status';
        $s   = $pdo->prepare("SELECT COUNT(*) FROM $table WHERE $col = ?");
        $s->execute([$status]);
        return (int)$s->fetchColumn();
    } catch (Exception) {
        return 0;
    }
}

// ---------------------------------------------------------------
// Site settings helpers (key/value store in `site_settings`)
// ---------------------------------------------------------------
function getSetting(PDO $pdo, string $key, string $default = ''): string
{
    try {
        $s = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = ?");
        $s->execute([$key]);
        $val = $s->fetchColumn();
        return $val !== false ? $val : $default;
    } catch (Exception) {
        return $default;
    }
}

function setSetting(PDO $pdo, string $key, string $value): void
{
    $existing = $pdo->prepare("SELECT id FROM site_settings WHERE setting_key = ?");
    $existing->execute([$key]);
    if ($existing->fetchColumn()) {
        $pdo->prepare("UPDATE site_settings SET setting_value = ? WHERE setting_key = ?")->execute([$value, $key]);
    } else {
        $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)")->execute([$key, $value]);
    }
}

$adminTheme = getSetting($pdo, 'admin_theme', 'dark');

$pendingBookings = countBadge($pdo, 'saloon_bookings');
$pendingLuxury   = countBadge($pdo, 'luxury_orders');
$pendingTools    = countBadge($pdo, 'tools_orders');
$unreadMessages  = countBadge($pdo, 'contact_messages', 'pending');
try {
    $unreadMessages = (int)$pdo->query("SELECT COUNT(*) FROM contact_messages WHERE is_read=0")->fetchColumn();
} catch (Exception) {
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle) ?> - Opulence Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --gold: #C9A84C;
            --dark: #0a0a0a;
            --dark2: #111;
            --dark3: #161616;
            --sidebar-w: 260px;
            --topbar-h: 64px;
            --border: #222;
            --text: #eee;
            --muted: #888;
            --success: #28a745;
            --danger: #dc3545;
            --info: #17a2b8;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0
        }

        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: #0d0d0d;
            color: var(--text);
            min-height: 100vh;
            display: flex
        }

        /* SIDEBAR */
        .sidebar {
            width: var(--sidebar-w);
            height: 100vh;
            background: var(--dark3);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            z-index: 100;
            transition: .3s;
            overflow: hidden
        }

        .sidebar-logo {
            padding: 24px 20px 20px;
            border-bottom: 1px solid var(--border);
            flex-shrink: 0
        }

        .sidebar-scroll {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            display: flex;
            flex-direction: column;
            min-height: 0
        }

        .sidebar-scroll::-webkit-scrollbar {
            width: 6px
        }

        .sidebar-scroll::-webkit-scrollbar-track {
            background: transparent
        }

        .sidebar-scroll::-webkit-scrollbar-thumb {
            background: #2a2a2a;
            border-radius: 10px
        }

        .sidebar-scroll::-webkit-scrollbar-thumb:hover {
            background: #3a3a3a
        }

        .sidebar-logo span {
            font-size: 20px;
            font-weight: 800;
            color: #fff;
            letter-spacing: 3px
        }

        .sidebar-logo em {
            display: block;
            font-style: normal;
            font-size: 11px;
            color: var(--gold);
            letter-spacing: 4px;
            margin-top: 2px
        }

        .sidebar-section {
            padding: 16px 12px 6px;
            font-size: 10px;
            color: #555;
            letter-spacing: 2px;
            text-transform: uppercase;
            font-weight: 600
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 11px 16px;
            border-radius: 8px;
            color: #999;
            text-decoration: none;
            font-size: 13.5px;
            font-weight: 500;
            margin: 2px 8px;
            transition: .15s;
            position: relative
        }

        .nav-item:hover {
            background: #1e1e1e;
            color: #fff
        }

        .nav-item.active {
            background: rgba(201, 168, 76, .12);
            color: var(--gold)
        }

        .nav-item i {
            width: 18px;
            text-align: center;
            font-size: 14px
        }

        .nav-badge {
            background: var(--danger);
            color: #fff;
            border-radius: 100px;
            font-size: 10px;
            padding: 2px 6px;
            font-weight: 700;
            min-width: 18px;
            text-align: center;
            margin-left: auto
        }

        .nav-badge.gold {
            background: var(--gold);
            color: #000
        }

        .sidebar-footer {
            flex-shrink: 0;
            padding: 16px;
            border-top: 1px solid var(--border)
        }

        .admin-chip {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px;
            border-radius: 8px;
            background: #1a1a1a
        }

        .admin-avatar {
            width: 34px;
            height: 34px;
            background: var(--gold);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: #000;
            font-size: 14px;
            flex-shrink: 0
        }

        .admin-info span {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #fff
        }

        .admin-info small {
            font-size: 11px;
            color: #666;
            text-transform: capitalize
        }

        .btn-logout {
            background: none;
            border: none;
            color: #666;
            cursor: pointer;
            padding: 6px;
            font-size: 13px;
            margin-left: auto;
            transition: .2s
        }

        .btn-logout:hover {
            color: #ff5555
        }

        .nav-item-logout {
            width: 100%;
            font-family: inherit
        }

        .nav-item-logout:hover {
            background: rgba(220, 53, 69, .1);
            color: #ff5555
        }

        /* MAIN */
        .main-wrap {
            margin-left: var(--sidebar-w);
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 100vh
        }

        .topbar {
            height: var(--topbar-h);
            background: var(--dark3);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            padding: 0 28px;
            gap: 16px;
            position: sticky;
            top: 0;
            z-index: 50
        }

        .topbar-title {
            font-size: 17px;
            font-weight: 700;
            color: #fff
        }

        .topbar-right {
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 12px
        }

        .topbar-btn {
            background: #1e1e1e;
            border: 1px solid var(--border);
            color: #888;
            border-radius: 8px;
            padding: 7px 14px;
            font-size: 13px;
            cursor: pointer;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: .15s
        }

        .topbar-btn:hover {
            border-color: #444;
            color: #fff
        }

        .page-body {
            padding: 28px;
            flex: 1
        }

        /* CARDS */
        .stat-card {
            background: var(--dark3);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 24px;
            position: relative;
            overflow: hidden
        }

        .stat-card .sc-num {
            font-size: 36px;
            font-weight: 800;
            color: #fff;
            line-height: 1
        }

        .stat-card .sc-label {
            font-size: 12px;
            color: var(--muted);
            margin-top: 6px;
            letter-spacing: .5px;
            text-transform: uppercase
        }

        .stat-card .sc-icon {
            position: absolute;
            right: 20px;
            top: 20px;
            width: 44px;
            height: 44px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px
        }

        .stat-card .sc-change {
            font-size: 12px;
            margin-top: 10px
        }

        .sc-icon.gold {
            background: rgba(201, 168, 76, .12);
            color: var(--gold)
        }

        .sc-icon.green {
            background: rgba(40, 167, 69, .12);
            color: #28a745
        }

        .sc-icon.blue {
            background: rgba(23, 162, 184, .12);
            color: #17a2b8
        }

        .sc-icon.red {
            background: rgba(220, 53, 69, .12);
            color: #dc3545
        }

        /* TABLE */
        .admin-table {
            width: 100%;
            border-collapse: collapse
        }

        .admin-table th {
            background: #1a1a1a;
            color: #888;
            font-size: 11px;
            letter-spacing: .5px;
            text-transform: uppercase;
            padding: 12px 16px;
            font-weight: 600;
            border-bottom: 1px solid var(--border);
            white-space: nowrap
        }

        .admin-table td {
            padding: 13px 16px;
            border-bottom: 1px solid #1a1a1a;
            font-size: 13.5px;
            color: #ccc;
            vertical-align: middle
        }

        .admin-table tr:hover td {
            background: rgba(255, 255, 255, .02)
        }

        .admin-table tr:last-child td {
            border-bottom: none
        }

        /* BADGES */
        .badge-status {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 100px;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: .3px
        }

        .badge-pending {
            background: rgba(201, 168, 76, .12);
            color: var(--gold)
        }

        .badge-confirmed {
            background: rgba(23, 162, 184, .12);
            color: #17a2b8
        }

        .badge-processing {
            background: rgba(23, 162, 184, .12);
            color: #17a2b8
        }

        .badge-completed,
        .badge-delivered {
            background: rgba(40, 167, 69, .12);
            color: #28a745
        }

        .badge-cancelled {
            background: rgba(220, 53, 69, .12);
            color: #dc3545
        }

        .badge-in_progress {
            background: rgba(255, 193, 7, .12);
            color: #ffc107
        }

        .badge-shipped {
            background: rgba(102, 16, 242, .12);
            color: #a580ff
        }

        .badge-no_show {
            background: #1a1a1a;
            color: #666
        }

        /* FORMS */
        .form-card {
            background: var(--dark3);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 28px
        }

        .form-label {
            color: #bbb;
            font-size: 13px;
            margin-bottom: 6px;
            font-weight: 500
        }

        .form-control,
        .form-select {
            background: #1e1e1e;
            border: 1px solid #2a2a2a;
            color: #fff;
            border-radius: 8px;
            padding: 11px 14px;
            font-size: 14px
        }

        .form-control:focus,
        .form-select:focus {
            background: #1e1e1e;
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(201, 168, 76, .12);
            color: #fff
        }

        .form-control::placeholder {
            color: #555
        }

        .form-select option {
            background: #1e1e1e;
            color: #fff
        }

        .btn-gold {
            background: var(--gold);
            color: #000;
            border: none;
            border-radius: 8px;
            padding: 11px 22px;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            transition: .2s
        }

        .btn-gold:hover {
            background: #b8932e
        }

        .btn-outline-del {
            background: none;
            border: 1px solid #3a1515;
            color: #ff5555;
            border-radius: 8px;
            padding: 7px 14px;
            font-size: 13px;
            cursor: pointer;
            transition: .15s
        }

        .btn-outline-del:hover {
            background: #3a1515
        }

        .btn-sm-action {
            background: none;
            border: none;
            cursor: pointer;
            padding: 5px 8px;
            border-radius: 6px;
            font-size: 13px;
            transition: .15s
        }

        .btn-sm-action:hover {
            background: #222
        }

        /* MISC */
        .section-title {
            font-size: 18px;
            font-weight: 700;
            color: #fff;
            margin-bottom: 4px
        }

        .section-sub {
            font-size: 13px;
            color: #666;
            margin-bottom: 20px
        }

        .img-thumb {
            width: 46px;
            height: 46px;
            border-radius: 8px;
            object-fit: cover;
            background: #1a1a1a
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #555
        }

        .empty-state i {
            font-size: 40px;
            margin-bottom: 14px;
            display: block
        }

        .toast-msg {
            position: fixed;
            bottom: 24px;
            right: 24px;
            background: #1e1e1e;
            border: 1px solid #333;
            color: #fff;
            padding: 14px 20px;
            border-radius: 10px;
            font-size: 14px;
            z-index: 9999;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, .4)
        }

        .toast-msg.success {
            border-color: #28a745;
            color: #6ee88a
        }

        .toast-msg.error {
            border-color: #dc3545;
            color: #ff8a8a
        }

        @media(max-width:768px) {
            .sidebar {
                transform: translateX(-100%)
            }

            .sidebar.open {
                transform: translateX(0)
            }

            .main-wrap {
                margin-left: 0
            }
        }

        body.theme-light {
            background: #f4f4f4;
            color: #1a1a1a;
        }

        body.theme-light .sidebar {
            background: #fff;
            border-right-color: #e2e2e2;
        }

        body.theme-light .sidebar-logo,
        body.theme-light .sidebar-footer {
            border-color: #e2e2e2;
        }

        body.theme-light .sidebar-logo span {
            color: #1a1a1a;
        }

        body.theme-light .nav-item {
            color: #555;
        }

        body.theme-light .nav-item:hover {
            background: #f0f0f0;
            color: #1a1a1a;
        }

        body.theme-light .nav-item.active {
            background: rgba(201, 168, 76, .15);
            color: #9c7c2e;
        }

        body.theme-light .admin-chip {
            background: #f0f0f0;
        }

        body.theme-light .admin-info span {
            color: #1a1a1a;
        }

        body.theme-light .topbar,
        body.theme-light .form-card,
        body.theme-light .stat-card {
            background: #fff;
            border-color: #e2e2e2;
        }

        body.theme-light .topbar-title {
            color: #1a1a1a;
        }

        body.theme-light .topbar-btn {
            background: #f0f0f0;
            border-color: #e2e2e2;
            color: #555;
        }

        body.theme-light .topbar-btn:hover {
            color: #1a1a1a;
            border-color: #ccc;
        }

        body.theme-light .stat-card .sc-num,
        body.theme-light .form-card h5 {
            color: #1a1a1a;
        }

        body.theme-light .admin-table th {
            background: #f4f4f4;
            color: #777;
            border-color: #e2e2e2;
        }

        body.theme-light .admin-table td {
            color: #333;
            border-color: #eee;
        }

        body.theme-light .admin-table tr:hover td {
            background: #fafafa;
        }

        body.theme-light .form-control,
        body.theme-light .form-select {
            background: #f7f7f7;
            border-color: #ddd;
            color: #1a1a1a;
        }

        body.theme-light .form-control:focus,
        body.theme-light .form-select:focus {
            background: #fff;
            color: #1a1a1a;
        }

        body.theme-light .form-control::placeholder {
            color: #999;
        }

        body.theme-light .form-label {
            color: #555;
        }

        body.theme-light .section-title {
            color: #1a1a1a;
        }

        body.theme-light .section-sub {
            color: #888;
        }

        body.theme-light .empty-state {
            color: #999;
        }

        body.theme-light .img-thumb {
            background: #f0f0f0;
        }
    </style>
</head>

<body class="<?= $adminTheme === 'light' ? 'theme-light' : '' ?>">

    <!-- SIDEBAR -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-logo">
            <span>OPULENCE</span>
            <em>ADMIN PANEL</em>
        </div>

        <div class="sidebar-scroll">
            <div class="sidebar-section">Overview</div>
            <a href="index.php" class="nav-item <?= $activePage === 'dashboard' ? 'active' : '' ?>">
                <i class="fa-solid fa-chart-line"></i> Dashboard
            </a>

            <div class="sidebar-section">Luxury Hair</div>
            <a href="luxury-products.php" class="nav-item <?= $activePage === 'lux-products' ? 'active' : '' ?>">
                <i class="fa-solid fa-wand-magic-sparkles"></i> Products
            </a>
            <a href="luxury-orders.php" class="nav-item <?= $activePage === 'lux-orders' ? 'active' : '' ?>">
                <i class="fa-solid fa-bag-shopping"></i> Orders
                <?php if ($pendingLuxury > 0): ?><span class="nav-badge"><?= $pendingLuxury ?></span><?php endif; ?>
            </a>

            <div class="sidebar-section">Tools</div>
            <a href="tools-products.php" class="nav-item <?= $activePage === 'tools-products' ? 'active' : '' ?>">
                <i class="fa-solid fa-screwdriver-wrench"></i> Products
            </a>
            <a href="tools-orders.php" class="nav-item <?= $activePage === 'tools-orders' ? 'active' : '' ?>">
                <i class="fa-solid fa-truck"></i> Orders
                <?php if ($pendingTools > 0): ?><span class="nav-badge"><?= $pendingTools ?></span><?php endif; ?>
            </a>

            <div class="sidebar-section">Saloon</div>
            <a href="bookings.php" class="nav-item <?= $activePage === 'bookings' ? 'active' : '' ?>">
                <i class="fa-solid fa-calendar-check"></i> Bookings
                <?php if ($pendingBookings > 0): ?><span class="nav-badge"><?= $pendingBookings ?></span><?php endif; ?>
            </a>

            <div class="sidebar-section">General</div>
            <a href="messages.php" class="nav-item <?= $activePage === 'messages' ? 'active' : '' ?>">
                <i class="fa-solid fa-envelope"></i> Messages
                <?php if ($unreadMessages > 0): ?><span class="nav-badge gold"><?= $unreadMessages ?></span><?php endif; ?>
            </a>
            <a href="settings.php" class="nav-item <?= $activePage === 'settings' ? 'active' : '' ?>">
                <i class="fa-solid fa-gear"></i> Settings
            </a>
            <a href="../index.html" class="nav-item" target="_blank">
                <i class="fa-solid fa-arrow-up-right-from-square"></i> View Site
            </a>

            <div class="sidebar-section">Account</div>
            <form method="POST" action="logout.php" id="logoutForm" style="margin:2px 8px">
                <button type="submit" class="nav-item nav-item-logout">
                    <i class="fa-solid fa-right-from-bracket"></i> Logout
                </button>
            </form>
        </div><!-- /sidebar-scroll -->

        <div class="sidebar-footer">
            <div class="admin-chip">
                <div class="admin-avatar"><?= strtoupper(substr($admin['name'], 0, 1)) ?></div>
                <div class="admin-info">
                    <span><?= htmlspecialchars($admin['name']) ?></span>
                    <small><?= htmlspecialchars($admin['role']) ?></small>
                </div>
                <button type="submit" form="logoutForm" class="btn-logout" title="Logout">
                    <i class="fa-solid fa-right-from-bracket"></i>
                </button>
            </div>
        </div>
    </aside>

    <!-- MAIN WRAP -->
    <div class="main-wrap">
        <div class="topbar">
            <button class="topbar-btn d-md-none" onclick="document.getElementById('sidebar').classList.toggle('open')">
                <i class="fa-solid fa-bars"></i>
            </button>
            <span class="topbar-title"><?= htmlspecialchars($pageTitle) ?></span>
            <div class="topbar-right">
                <span style="font-size:13px;color:#555"><?= date('D, d M Y') ?></span>
                <a href="../index.html" class="topbar-btn" target="_blank">
                    <i class="fa-solid fa-globe"></i> View Site
                </a>
            </div>
        </div>
        <div class="page-body">
            <!-- PAGE CONTENT STARTS HERE -->
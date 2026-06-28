<?php
$pageTitle  = 'Dashboard';
$activePage = 'dashboard';
require_once __DIR__ . '/_layout.php';

// Stats
$stats = [];
try {
    $stats['luxury_products'] = (int)$pdo->query("SELECT COUNT(*) FROM luxury_products WHERE in_stock=1")->fetchColumn();
    $stats['tools_products']  = (int)$pdo->query("SELECT COUNT(*) FROM tools_products WHERE in_stock=1")->fetchColumn();
    $stats['total_bookings']  = (int)$pdo->query("SELECT COUNT(*) FROM saloon_bookings")->fetchColumn();
    $stats['luxury_revenue']  = (float)$pdo->query("SELECT COALESCE(SUM(total),0) FROM luxury_orders WHERE status NOT IN('cancelled')")->fetchColumn();
    $stats['tools_revenue']   = (float)$pdo->query("SELECT COALESCE(SUM(total),0) FROM tools_orders WHERE status NOT IN('cancelled')")->fetchColumn();
    $stats['pending_orders']  = (int)$pdo->query("SELECT (SELECT COUNT(*) FROM luxury_orders WHERE status='pending')+(SELECT COUNT(*) FROM tools_orders WHERE status='pending')")->fetchColumn();
    $stats['messages']        = (int)$pdo->query("SELECT COUNT(*) FROM contact_messages WHERE is_read=0")->fetchColumn();

    // Recent bookings
    $recentBookings = $pdo->query(
        "SELECT * FROM saloon_bookings ORDER BY created_at DESC LIMIT 5"
    )->fetchAll();

    // Recent luxury orders
    $recentLuxury = $pdo->query(
        "SELECT * FROM luxury_orders ORDER BY created_at DESC LIMIT 5"
    )->fetchAll();

    // Recent tools orders
    $recentTools = $pdo->query(
        "SELECT * FROM tools_orders ORDER BY created_at DESC LIMIT 4"
    )->fetchAll();
} catch (Exception $e) {
    $stats = array_fill_keys(['luxury_products', 'tools_products', 'total_bookings', 'luxury_revenue', 'tools_revenue', 'pending_orders', 'messages'], 0);
    $recentBookings = $recentLuxury = $recentTools = [];
}

$totalRevenue = $stats['luxury_revenue'] + $stats['tools_revenue'];

function fmtN(float $n): string
{
    return '₦' . number_format($n, 0, '.', ',');
}
function statusBadge(string $s): string
{
    return '<span class="badge-status badge-' . $s . '">' . ucfirst(str_replace('_', ' ', $s)) . '</span>';
}
?>

<!-- STAT CARDS -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="sc-icon gold"><i class="fa-solid fa-chart-line"></i></div>
            <div class="sc-num"><?= fmtN($totalRevenue) ?></div>
            <div class="sc-label">Total Revenue</div>
            <div class="sc-change" style="color:#888">All confirmed orders</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="sc-icon blue"><i class="fa-solid fa-calendar-check"></i></div>
            <div class="sc-num"><?= $stats['total_bookings'] ?></div>
            <div class="sc-label">Total Bookings</div>
            <div class="sc-change" style="color:var(--gold)"><?= $pendingBookings ?> pending</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="sc-icon green"><i class="fa-solid fa-bag-shopping"></i></div>
            <div class="sc-num"><?= $stats['pending_orders'] ?></div>
            <div class="sc-label">Pending Orders</div>
            <div class="sc-change" style="color:#888">Requires attention</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="sc-icon red"><i class="fa-solid fa-envelope"></i></div>
            <div class="sc-num"><?= $stats['messages'] ?></div>
            <div class="sc-label">Unread Messages</div>
            <div class="sc-change"><a href="messages.php" style="color:var(--gold);font-size:12px">View all →</a></div>
        </div>
    </div>
</div>

<!-- QUICK ACTIONS -->
<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="form-card" style="padding:18px 24px">
            <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
                <span style="color:#888;font-size:13px;font-weight:600">QUICK ACTIONS</span>
                <a href="luxury-products.php?action=add" class="topbar-btn" style="color:var(--gold);border-color:rgba(201,168,76,.3)">
                    <i class="fa-solid fa-plus"></i> Add Hair Product
                </a>
                <a href="tools-products.php?action=add" class="topbar-btn" style="color:var(--gold);border-color:rgba(201,168,76,.3)">
                    <i class="fa-solid fa-plus"></i> Add Tool
                </a>
                <a href="bookings.php" class="topbar-btn">
                    <i class="fa-solid fa-calendar"></i> View Bookings
                </a>
                <a href="luxury-orders.php" class="topbar-btn">
                    <i class="fa-solid fa-bag-shopping"></i> Hair Orders
                </a>
                <a href="tools-orders.php" class="topbar-btn">
                    <i class="fa-solid fa-truck"></i> Tool Orders
                </a>
            </div>
        </div>
    </div>
</div>

<!-- TABLES ROW -->
<div class="row g-3">
    <!-- Recent Bookings -->
    <div class="col-12 col-xl-6">
        <div class="form-card" style="padding:0;overflow:hidden">
            <div style="padding:20px 24px 16px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--border)">
                <div class="section-title" style="margin-bottom:0">Recent Bookings</div>
                <a href="bookings.php" style="font-size:12px;color:var(--gold);text-decoration:none">View all →</a>
            </div>
            <?php if (empty($recentBookings)): ?>
                <div class="empty-state"><i class="fa-solid fa-calendar-xmark"></i>No bookings yet</div>
            <?php else: ?>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Client</th>
                            <th>Service</th>
                            <th>Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentBookings as $b): ?>
                            <tr>
                                <td><?= htmlspecialchars($b['fullname']) ?><br><small style="color:#555"><?= htmlspecialchars($b['phone']) ?></small></td>
                                <td style="max-width:140px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($b['service']) ?></td>
                                <td><small><?= date('d M', strtotime($b['appointment_date'])) ?><br><?= htmlspecialchars($b['appointment_time']) ?></small></td>
                                <td><?= statusBadge($b['status']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Hair Orders -->
    <div class="col-12 col-xl-6">
        <div class="form-card" style="padding:0;overflow:hidden">
            <div style="padding:20px 24px 16px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--border)">
                <div class="section-title" style="margin-bottom:0">Recent Hair Orders</div>
                <a href="luxury-orders.php" style="font-size:12px;color:var(--gold);text-decoration:none">View all →</a>
            </div>
            <?php if (empty($recentLuxury)): ?>
                <div class="empty-state"><i class="fa-solid fa-bag-shopping"></i>No orders yet</div>
            <?php else: ?>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Ref</th>
                            <th>Customer</th>
                            <th>Total</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentLuxury as $o): ?>
                            <tr>
                                <td style="font-family:monospace;font-size:12px;color:var(--gold)"><?= htmlspecialchars($o['order_ref']) ?></td>
                                <td><?= htmlspecialchars($o['customer_name']) ?></td>
                                <td><?= fmtN((float)$o['total']) ?></td>
                                <td><?= statusBadge($o['status']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Revenue split -->
    <div class="col-12 col-md-6">
        <div class="form-card">
            <div class="section-title">Revenue Breakdown</div>
            <div class="section-sub">All time, excluding cancelled</div>
            <div style="display:flex;flex-direction:column;gap:14px">
                <div>
                    <div style="display:flex;justify-content:space-between;margin-bottom:6px">
                        <span style="font-size:13px;color:#bbb">Opulence Luxury (Hair)</span>
                        <span style="font-size:13px;font-weight:700;color:var(--gold)"><?= fmtN($stats['luxury_revenue']) ?></span>
                    </div>
                    <?php $pct = $totalRevenue > 0 ? round($stats['luxury_revenue'] / $totalRevenue * 100) : 0; ?>
                    <div style="background:#1a1a1a;border-radius:100px;height:6px">
                        <div style="background:var(--gold);height:6px;border-radius:100px;width:<?= $pct ?>%"></div>
                    </div>
                </div>
                <div>
                    <div style="display:flex;justify-content:space-between;margin-bottom:6px">
                        <span style="font-size:13px;color:#bbb">Opulence Tools</span>
                        <span style="font-size:13px;font-weight:700;color:#17a2b8"><?= fmtN($stats['tools_revenue']) ?></span>
                    </div>
                    <?php $pct2 = $totalRevenue > 0 ? round($stats['tools_revenue'] / $totalRevenue * 100) : 0; ?>
                    <div style="background:#1a1a1a;border-radius:100px;height:6px">
                        <div style="background:#17a2b8;height:6px;border-radius:100px;width:<?= $pct2 ?>%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Inventory summary -->
    <div class="col-12 col-md-6">
        <div class="form-card">
            <div class="section-title">Inventory Summary</div>
            <div class="section-sub">Active products in store</div>
            <div style="display:flex;gap:16px;flex-wrap:wrap">
                <div style="flex:1;min-width:120px;background:#1a1a1a;border-radius:12px;padding:18px;text-align:center">
                    <div style="font-size:30px;font-weight:800;color:var(--gold)"><?= $stats['luxury_products'] ?></div>
                    <div style="font-size:12px;color:#888;margin-top:4px">Hair Products</div>
                    <a href="luxury-products.php" style="font-size:11px;color:#555;text-decoration:none">Manage →</a>
                </div>
                <div style="flex:1;min-width:120px;background:#1a1a1a;border-radius:12px;padding:18px;text-align:center">
                    <div style="font-size:30px;font-weight:800;color:#17a2b8"><?= $stats['tools_products'] ?></div>
                    <div style="font-size:12px;color:#888;margin-top:4px">Tool Products</div>
                    <a href="tools-products.php" style="font-size:11px;color:#555;text-decoration:none">Manage →</a>
                </div>
                <div style="flex:1;min-width:120px;background:#1a1a1a;border-radius:12px;padding:18px;text-align:center">
                    <div style="font-size:30px;font-weight:800;color:#28a745"><?= $stats['total_bookings'] ?></div>
                    <div style="font-size:12px;color:#888;margin-top:4px">Total Bookings</div>
                    <a href="bookings.php" style="font-size:11px;color:#555;text-decoration:none">Manage →</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/_footer.php'; ?>
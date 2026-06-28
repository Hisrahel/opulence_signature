<?php
// admin/luxury-orders.php
$pageTitle  = 'Hair Orders (Luxury)';
$activePage = 'lux-orders';
$orderTable = 'luxury_orders';
$orderType  = 'luxury';
require_once __DIR__ . '/_layout.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['_action'] ?? '';
    if ($act === 'update_status') {
        $id     = (int)($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? '';
        $allowed = ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled'];
        if ($id && in_array($status, $allowed)) {
            $pdo->prepare("UPDATE luxury_orders SET status=? WHERE id=?")->execute([$status, $id]);
        }
        header('Location: luxury-orders.php?success=' . urlencode('Order status updated.'));
        exit;
    }
    if ($act === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM luxury_orders WHERE id=?")->execute([$id]);
        header('Location: luxury-orders.php?success=' . urlencode('Order deleted.'));
        exit;
    }
}

$filterStatus = $_GET['status'] ?? 'all';
$search       = trim($_GET['q']  ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 20;
$offset       = ($page - 1) * $perPage;

$where  = [];
$params = [];
if ($filterStatus !== 'all') {
    $where[] = 'status=?';
    $params[] = $filterStatus;
}
if ($search) {
    $where[] = '(customer_name LIKE ? OR customer_phone LIKE ? OR order_ref LIKE ?)';
    $t = "%$search%";
    $params = array_merge($params, [$t, $t, $t]);
}
$wSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$cStmt = $pdo->prepare("SELECT COUNT(*) FROM luxury_orders $wSQL");
$cStmt->execute($params);
$total = (int)$cStmt->fetchColumn();
$pages = max(1, ceil($total / $perPage));

$stmt = $pdo->prepare("SELECT * FROM luxury_orders $wSQL ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Revenue
$rev = (float)$pdo->query("SELECT COALESCE(SUM(total),0) FROM luxury_orders WHERE status NOT IN('cancelled')")->fetchColumn();

$allStatuses = [
    'pending' => 'Pending',
    'confirmed' => 'Confirmed',
    'processing' => 'Processing',
    'shipped' => 'Shipped',
    'delivered' => 'Delivered',
    'cancelled' => 'Cancelled'
];

function fmtO(float $n): string
{
    return '₦' . number_format($n, 0, '.', ',');
}
function sBadge3(string $s): string
{
    return '<span class="badge-status badge-' . $s . '">' . ucfirst($s) . '</span>';
}
?>

<!-- HEADER -->
<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:20px">
    <div>
        <div class="section-title">Hair Orders</div>
        <div class="section-sub">Total revenue: <strong style="color:var(--gold)"><?= fmtO($rev) ?></strong></div>
    </div>
</div>

<!-- STATUS FILTER PILLS -->
<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:20px">
    <?php
    $statColors = [
        'pending' => '#C9A84C',
        'confirmed' => '#17a2b8',
        'processing' => '#17a2b8',
        'shipped' => '#a580ff',
        'delivered' => '#28a745',
        'cancelled' => '#dc3545'
    ];
    foreach ($allStatuses as $sk => $sv):
        $r2 = $pdo->prepare("SELECT COUNT(*) FROM luxury_orders WHERE status=?");
        $r2->execute([$sk]);
        $n = (int)$r2->fetchColumn();
        $active = $filterStatus === $sk;
    ?>
        <a href="?status=<?= $sk ?>"
            style="padding:7px 14px;border-radius:100px;font-size:13px;text-decoration:none;display:flex;align-items:center;gap:7px;
       background:<?= $active ? 'rgba(201,168,76,.12)' : '#1a1a1a' ?>;
       border:1px solid <?= $active ? 'var(--gold)' : '#2a2a2a' ?>;color:<?= $active ? 'var(--gold)' : '#888' ?>">
            <?= $sv ?> <span style="background:<?= $statColors[$sk] ?>;color:#fff;border-radius:100px;padding:1px 7px;font-size:11px;font-weight:700"><?= $n ?></span>
        </a>
    <?php endforeach; ?>
    <?php if ($filterStatus !== 'all'): ?><a href="luxury-orders.php" style="padding:7px 14px;border-radius:100px;font-size:13px;text-decoration:none;background:#1a1a1a;border:1px solid #333;color:#666">Clear</a><?php endif; ?>
</div>

<!-- SEARCH -->
<form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px;align-items:center">
    <input type="hidden" name="status" value="<?= htmlspecialchars($filterStatus) ?>">
    <input type="text" name="q" class="form-control" placeholder="Search name, phone, order ref..." style="max-width:300px"
        value="<?= htmlspecialchars($search) ?>">
    <button type="submit" class="btn-gold" style="padding:10px 18px">Search</button>
    <span style="color:#555;font-size:13px"><?= $total ?> orders</span>
</form>

<!-- TABLE -->
<div class="form-card" style="padding:0;overflow:hidden">
    <?php if (empty($orders)): ?>
        <div class="empty-state"><i class="fa-solid fa-bag-shopping"></i>No orders found.</div>
    <?php else: ?>
        <div style="overflow-x:auto">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Order Ref</th>
                        <th>Customer</th>
                        <th>Items</th>
                        <th>Subtotal</th>
                        <th>Delivery</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $o):
                        $items = json_decode($o['items_json'], true) ?? [];
                    ?>
                        <tr>
                            <td style="font-family:monospace;font-size:12px;color:var(--gold)"><?= htmlspecialchars($o['order_ref']) ?></td>
                            <td>
                                <strong style="color:#fff"><?= htmlspecialchars($o['customer_name']) ?></strong><br>
                                <small><a href="tel:<?= htmlspecialchars($o['customer_phone']) ?>" style="color:#555"><?= htmlspecialchars($o['customer_phone']) ?></a></small>
                                <?php if ($o['customer_email']): ?><br><small style="color:#444"><?= htmlspecialchars($o['customer_email']) ?></small><?php endif; ?>
                            </td>
                            <td>
                                <?php foreach (array_slice($items, 0, 2) as $item): ?>
                                    <div style="font-size:12px;color:#bbb"><?= htmlspecialchars($item['name']) ?> ×<?= $item['qty'] ?></div>
                                <?php endforeach; ?>
                                <?php if (count($items) > 2): ?><small style="color:#555">+<?= count($items) - 2 ?> more</small><?php endif; ?>
                            </td>
                            <td><?= fmtO((float)$o['subtotal']) ?></td>
                            <td><?= $o['delivery_fee'] > 0 ? fmtO((float)$o['delivery_fee']) : '<span style="color:#28a745">Free</span>' ?></td>
                            <td style="font-weight:700;color:var(--gold)"><?= fmtO((float)$o['total']) ?></td>
                            <td><?= sBadge3($o['status']) ?></td>
                            <td><small style="color:#888"><?= date('d M Y', strtotime($o['created_at'])) ?></small></td>
                            <td>
                                <div style="display:flex;align-items:center;gap:6px">
                                    <form method="POST" style="display:flex;gap:4px;align-items:center">
                                        <input type="hidden" name="_action" value="update_status">
                                        <input type="hidden" name="id" value="<?= $o['id'] ?>">
                                        <select name="status" class="form-select" style="font-size:12px;padding:5px 8px;min-width:120px" onchange="this.form.submit()">
                                            <?php foreach ($allStatuses as $sk => $sv): ?>
                                                <option value="<?= $sk ?>" <?= $o['status'] === $sk ? 'selected' : '' ?>><?= $sv ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </form>
                                    <?php if ($o['delivery_address']): ?>
                                        <button class="btn-sm-action" style="color:#888" title="Delivery: <?= htmlspecialchars($o['delivery_address']) ?>"
                                            onclick="alert('Delivery address:\n<?= addslashes(htmlspecialchars($o['delivery_address'])) ?>\n\nNotes:\n<?= addslashes(htmlspecialchars($o['notes'] ?? 'None')) ?>')">
                                            <i class="fa-solid fa-location-dot"></i>
                                        </button>
                                    <?php endif; ?>
                                    <form method="POST" style="display:inline" onsubmit="return confirm('Delete this order?')">
                                        <input type="hidden" name="_action" value="delete">
                                        <input type="hidden" name="id" value="<?= $o['id'] ?>">
                                        <button type="submit" class="btn-sm-action" style="color:#dc3545"><i class="fa-solid fa-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($pages > 1): ?>
            <div style="padding:16px 20px;border-top:1px solid var(--border);display:flex;gap:6px;flex-wrap:wrap">
                <?php for ($i = 1; $i <= $pages; $i++): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"
                        style="padding:6px 12px;border-radius:6px;text-decoration:none;font-size:13px;
           background:<?= $i === $page ? 'var(--gold)' : '#1a1a1a' ?>;
           color:<?= $i === $page ? '#000' : '#888' ?>;border:1px solid <?= $i === $page ? 'var(--gold)' : '#2a2a2a' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/_footer.php'; ?>
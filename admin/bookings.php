<?php
$pageTitle  = 'Salon Bookings';
$activePage = 'bookings';
require_once __DIR__ . '/_layout.php';

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['_action'] ?? '';
    if ($act === 'update_status') {
        $id     = (int)($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? '';
        $allowed = ['pending', 'confirmed', 'in_progress', 'completed', 'cancelled', 'no_show'];
        if ($id && in_array($status, $allowed)) {
            $pdo->prepare("UPDATE saloon_bookings SET status=? WHERE id=?")->execute([$status, $id]);
        }
        header('Location: bookings.php?success=' . urlencode('Booking status updated.'));
        exit;
    }
    if ($act === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM saloon_bookings WHERE id=?")->execute([$id]);
        header('Location: bookings.php?success=' . urlencode('Booking deleted.'));
        exit;
    }
}

// Filters
$filterStatus   = $_GET['status']   ?? 'all';
$filterLocation = $_GET['location'] ?? 'all';
$search         = trim($_GET['q']   ?? '');
$page           = max(1, (int)($_GET['page'] ?? 1));
$perPage        = 20;
$offset         = ($page - 1) * $perPage;

$where  = [];
$params = [];
if ($filterStatus !== 'all') {
    $where[] = 'status = ?';
    $params[] = $filterStatus;
}
if ($filterLocation !== 'all') {
    $where[] = 'location = ?';
    $params[] = $filterLocation;
}
if ($search) {
    $where[] = '(fullname LIKE ? OR phone LIKE ? OR email LIKE ? OR booking_ref LIKE ? OR service LIKE ?)';
    $term = "%$search%";
    $params = array_merge($params, [$term, $term, $term, $term, $term]);
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total     = (int)$pdo->prepare("SELECT COUNT(*) FROM saloon_bookings $whereSQL")->execute($params) ? 0 : 0;
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM saloon_bookings $whereSQL");
$countStmt->execute($params);
$total     = (int)$countStmt->fetchColumn();
$pages     = max(1, ceil($total / $perPage));

$stmt = $pdo->prepare("SELECT * FROM saloon_bookings $whereSQL ORDER BY appointment_date ASC, appointment_time ASC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$bookings = $stmt->fetchAll();

// Status summary counts
$summary = [];
foreach (['pending', 'confirmed', 'in_progress', 'completed', 'cancelled', 'no_show'] as $s) {
    $r = $pdo->prepare("SELECT COUNT(*) FROM saloon_bookings WHERE status=?");
    $r->execute([$s]);
    $summary[$s] = (int)$r->fetchColumn();
}

$allStatuses = [
    'pending' => 'Pending',
    'confirmed' => 'Confirmed',
    'in_progress' => 'In Progress',
    'completed' => 'Completed',
    'cancelled' => 'Cancelled',
    'no_show' => 'No Show'
];

function statusBadge2(string $s): string
{
    return '<span class="badge-status badge-' . $s . '">' . ucfirst(str_replace('_', ' ', $s)) . '</span>';
}
?>

<!-- SUMMARY PILLS -->
<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:24px">
    <?php
    $colors = [
        'pending' => '#C9A84C',
        'confirmed' => '#17a2b8',
        'in_progress' => '#ffc107',
        'completed' => '#28a745',
        'cancelled' => '#dc3545',
        'no_show' => '#666'
    ];
    foreach ($summary as $s => $n):
        $active = $filterStatus === $s;
    ?>
        <a href="?status=<?= $s ?><?= $filterLocation !== 'all' ? '&location=' . $filterLocation : '' ?>"
            style="padding:8px 16px;border-radius:100px;font-size:13px;text-decoration:none;display:flex;align-items:center;gap:8px;
       background:<?= $active ? 'rgba(201,168,76,.12)' : '#1a1a1a' ?>;
       border:1px solid <?= $active ? 'var(--gold)' : '#2a2a2a' ?>;
       color:<?= $active ? 'var(--gold)' : '#888' ?>">
            <?= ucfirst(str_replace('_', ' ', $s)) ?>
            <span style="background:<?= $colors[$s] ?>;color:#000;border-radius:100px;padding:1px 7px;font-size:11px;font-weight:700;min-width:20px;text-align:center;color:#fff"><?= $n ?></span>
        </a>
    <?php endforeach; ?>
    <?php if ($filterStatus !== 'all'): ?>
        <a href="bookings.php" style="padding:8px 16px;border-radius:100px;font-size:13px;text-decoration:none;background:#1a1a1a;border:1px solid #333;color:#666">Clear filter</a>
    <?php endif; ?>
</div>

<!-- SEARCH & FILTER BAR -->
<form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px;align-items:center">
    <input type="hidden" name="status" value="<?= htmlspecialchars($filterStatus) ?>">
    <input type="text" name="q" class="form-control" placeholder="Search name, phone, ref..." style="max-width:280px"
        value="<?= htmlspecialchars($search) ?>">
    <select name="location" class="form-select" style="max-width:180px">
        <option value="all" <?= $filterLocation === 'all' ? 'selected' : '' ?>>All Locations</option>
        <option value="Lagos" <?= $filterLocation === 'Lagos' ? 'selected' : '' ?>>Lagos</option>
        <option value="Osogbo" <?= $filterLocation === 'Osogbo' ? 'selected' : '' ?>>Osogbo</option>
    </select>
    <button type="submit" class="btn-gold" style="padding:10px 18px">Search</button>
    <span style="color:#555;font-size:13px"><?= $total ?> bookings</span>
</form>

<!-- TABLE -->
<div class="form-card" style="padding:0;overflow:hidden">
    <?php if (empty($bookings)): ?>
        <div class="empty-state"><i class="fa-solid fa-calendar-xmark"></i>No bookings found.</div>
    <?php else: ?>
        <div style="overflow-x:auto">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Ref</th>
                        <th>Client</th>
                        <th>Service</th>
                        <th>Location</th>
                        <th>Date & Time</th>
                        <th>Contact</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookings as $b): ?>
                        <tr>
                            <td style="font-family:monospace;font-size:12px;color:var(--gold)"><?= htmlspecialchars($b['booking_ref']) ?></td>
                            <td>
                                <strong style="color:#fff"><?= htmlspecialchars($b['fullname']) ?></strong><br>
                                <small style="color:#555"><a href="tel:<?= htmlspecialchars($b['phone']) ?>" style="color:#555"><?= htmlspecialchars($b['phone']) ?></a></small>
                            </td>
                            <td style="max-width:160px">
                                <span style="font-size:13px"><?= htmlspecialchars($b['service']) ?></span><br>
                                <small style="color:#555"><?= htmlspecialchars($b['duration']) ?></small>
                            </td>
                            <td><span style="font-size:12px;background:#1a1a1a;padding:3px 10px;border-radius:100px;color:#bbb"><?= htmlspecialchars($b['location']) ?></span></td>
                            <td>
                                <strong style="color:#fff;font-size:13px"><?= date('d M Y', strtotime($b['appointment_date'])) ?></strong><br>
                                <small style="color:#888"><?= htmlspecialchars($b['appointment_time']) ?></small>
                            </td>
                            <td>
                                <small style="color:#888"><?= htmlspecialchars($b['preferred_contact']) ?></small><br>
                                <small><a href="mailto:<?= htmlspecialchars($b['email']) ?>" style="color:#555;font-size:11px"><?= htmlspecialchars($b['email']) ?></a></small>
                            </td>
                            <td><?= statusBadge2($b['status']) ?></td>
                            <td>
                                <!-- Status update dropdown -->
                                <div style="display:flex;align-items:center;gap:6px">
                                    <form method="POST" style="display:inline;display:flex;gap:4px;align-items:center">
                                        <input type="hidden" name="_action" value="update_status">
                                        <input type="hidden" name="id" value="<?= $b['id'] ?>">
                                        <select name="status" class="form-select" style="font-size:12px;padding:5px 8px;min-width:120px"
                                            onchange="this.form.submit()">
                                            <?php foreach ($allStatuses as $sk => $sv): ?>
                                                <option value="<?= $sk ?>" <?= $b['status'] === $sk ? 'selected' : '' ?>><?= $sv ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </form>
                                    <?php if ($b['notes']): ?>
                                        <button class="btn-sm-action" style="color:#888" title="<?= htmlspecialchars($b['notes']) ?>"
                                            onclick="alert('Note:\n<?= addslashes(htmlspecialchars($b['notes'])) ?>')">
                                            <i class="fa-solid fa-note-sticky"></i>
                                        </button>
                                    <?php endif; ?>
                                    <form method="POST" style="display:inline" onsubmit="return confirm('Delete this booking?')">
                                        <input type="hidden" name="_action" value="delete">
                                        <input type="hidden" name="id" value="<?= $b['id'] ?>">
                                        <button type="submit" class="btn-sm-action" style="color:#dc3545"><i class="fa-solid fa-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($pages > 1): ?>
            <div style="padding:16px 20px;border-top:1px solid var(--border);display:flex;gap:6px;flex-wrap:wrap;align-items:center">
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
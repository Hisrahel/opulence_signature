<?php
$pageTitle  = 'Messages';
$activePage = 'messages';
require_once __DIR__ . '/_layout.php';

$action = $_GET['action'] ?? 'list';
$viewId = (int)($_GET['id'] ?? 0);

// ---------------------------------------------------------------
// Handle POST (toggle read / delete / mark all read)
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['_action'] ?? '';

    // ---- DELETE ----
    if ($act === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare("DELETE FROM contact_messages WHERE id=?")->execute([$id]);
            header('Location: messages.php?success=' . urlencode('Message deleted.'));
        } else {
            header('Location: messages.php?error=' . urlencode('Invalid message.'));
        }
        exit;
    }

    // ---- TOGGLE READ/UNREAD ----
    if ($act === 'toggle') {
        $id  = (int)($_POST['id'] ?? 0);
        $cur = (int)($_POST['current'] ?? 0);
        if ($id > 0) {
            $pdo->prepare("UPDATE contact_messages SET is_read=? WHERE id=?")->execute([$cur ? 0 : 1, $id]);
            header('Location: messages.php?success=' . urlencode('Message updated.'));
        } else {
            header('Location: messages.php?error=' . urlencode('Invalid message.'));
        }
        exit;
    }

    // ---- MARK ALL READ ----
    if ($act === 'mark_all_read') {
        $pdo->exec("UPDATE contact_messages SET is_read=1 WHERE is_read=0");
        header('Location: messages.php?success=' . urlencode('All messages marked as read.'));
        exit;
    }
}

// ---------------------------------------------------------------
// Load single message for viewing (and mark as read)
// ---------------------------------------------------------------
$viewMessage = null;
if ($action === 'view' && $viewId) {
    $s = $pdo->prepare("SELECT * FROM contact_messages WHERE id=?");
    $s->execute([$viewId]);
    $viewMessage = $s->fetch();
    if (!$viewMessage) {
        header('Location: messages.php?error=' . urlencode('Message not found.'));
        exit;
    }
    if ((int)$viewMessage['is_read'] === 0) {
        $pdo->prepare("UPDATE contact_messages SET is_read=1 WHERE id=?")->execute([$viewId]);
        $viewMessage['is_read'] = 1;
    }
}

// ---------------------------------------------------------------
// Load list
// ---------------------------------------------------------------
$filter = $_GET['filter'] ?? 'all';
$validFilters = ['all', 'unread', 'read'];
if (!in_array($filter, $validFilters, true)) $filter = 'all';

if ($filter === 'unread') {
    $stmt = $pdo->query("SELECT * FROM contact_messages WHERE is_read=0 ORDER BY created_at DESC");
} elseif ($filter === 'read') {
    $stmt = $pdo->query("SELECT * FROM contact_messages WHERE is_read=1 ORDER BY created_at DESC");
} else {
    $stmt = $pdo->query("SELECT * FROM contact_messages ORDER BY created_at DESC");
}
$messages = $stmt->fetchAll();

$totalUnread = (int)$pdo->query("SELECT COUNT(*) FROM contact_messages WHERE is_read=0")->fetchColumn();

function msgExcerpt(string $text, int $len = 70): string
{
    $text = trim(preg_replace('/\s+/', ' ', $text));
    return mb_strlen($text) > $len ? mb_substr($text, 0, $len) . '…' : $text;
}
?>

<?php if ($action === 'view' && $viewMessage): ?>
    <!-- ===== SINGLE MESSAGE VIEW ===== -->
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:24px">
        <a href="messages.php" style="color:#888;text-decoration:none;font-size:13px">
            <i class="fa-solid fa-arrow-left me-1"></i>Messages
        </a>
        <span style="color:#444">/</span>
        <span style="color:#fff;font-size:14px">View Message</span>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="form-card">
                <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:20px;flex-wrap:wrap">
                    <div>
                        <h5 style="color:#fff;margin-bottom:4px"><?= htmlspecialchars($viewMessage['name']) ?></h5>
                        <span style="font-size:12px;background:#1a1a1a;padding:4px 10px;border-radius:100px;color:#bbb">
                            <?= htmlspecialchars($viewMessage['enquiry'] ?: 'General Enquiry') ?>
                        </span>
                    </div>
                    <span style="font-size:12px;color:#666"><?= date('D, d M Y · g:i A', strtotime($viewMessage['created_at'])) ?></span>
                </div>
                <div style="background:#1a1a1a;border-radius:10px;padding:18px;color:#ddd;font-size:14px;line-height:1.7;white-space:pre-wrap"><?= htmlspecialchars($viewMessage['message']) ?></div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="form-card mb-3">
                <label class="form-label">Contact Details</label>
                <div style="display:flex;flex-direction:column;gap:12px;margin-top:8px">
                    <div style="display:flex;align-items:center;gap:10px">
                        <i class="fa-solid fa-envelope" style="color:var(--gold);width:16px"></i>
                        <a href="mailto:<?= htmlspecialchars($viewMessage['email']) ?>" style="color:#ddd;text-decoration:none;font-size:13.5px"><?= htmlspecialchars($viewMessage['email']) ?></a>
                    </div>
                    <?php if (!empty($viewMessage['phone'])): ?>
                        <div style="display:flex;align-items:center;gap:10px">
                            <i class="fa-solid fa-phone" style="color:var(--gold);width:16px"></i>
                            <a href="tel:<?= htmlspecialchars($viewMessage['phone']) ?>" style="color:#ddd;text-decoration:none;font-size:13.5px"><?= htmlspecialchars($viewMessage['phone']) ?></a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-card">
                <div style="display:flex;flex-direction:column;gap:10px">
                    <a href="mailto:<?= htmlspecialchars($viewMessage['email']) ?>" class="btn-gold" style="text-align:center;text-decoration:none">
                        <i class="fa-solid fa-reply me-1"></i>Reply by Email
                    </a>
                    <?php if (!empty($viewMessage['phone'])): ?>
                        <a href="https://wa.me/<?= htmlspecialchars(preg_replace('/[^0-9]/', '', $viewMessage['phone'])) ?>" target="_blank"
                            style="background:#1e1e1e;color:#ddd;border:1px solid #2a2a2a;border-radius:8px;padding:11px;text-align:center;text-decoration:none;font-size:14px">
                            <i class="fa-brands fa-whatsapp me-1"></i>WhatsApp
                        </a>
                    <?php endif; ?>
                    <form method="POST" onsubmit="return confirm('Delete this message? This cannot be undone.')">
                        <input type="hidden" name="_action" value="delete">
                        <input type="hidden" name="id" value="<?= (int)$viewMessage['id'] ?>">
                        <button type="submit" style="width:100%;background:none;border:1px solid #3a1515;color:#ff5555;border-radius:8px;padding:11px;font-size:14px;cursor:pointer">
                            <i class="fa-solid fa-trash me-1"></i>Delete Message
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

<?php else: ?>
    <!-- ===== MESSAGE LIST ===== -->
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:20px">
        <div>
            <div class="section-title">Messages</div>
            <div class="section-sub"><?= count($messages) ?> messages · <?= $totalUnread ?> unread</div>
        </div>
        <?php if ($totalUnread > 0): ?>
            <form method="POST">
                <input type="hidden" name="_action" value="mark_all_read">
                <button type="submit" class="topbar-btn" style="border:1px solid #2a2a2a">
                    <i class="fa-solid fa-check-double"></i> Mark all as read
                </button>
            </form>
        <?php endif; ?>
    </div>

    <div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap">
        <?php foreach (['all' => 'All', 'unread' => 'Unread', 'read' => 'Read'] as $f => $label): ?>
            <a href="?filter=<?= $f ?>" style="padding:6px 14px;border-radius:100px;font-size:13px;text-decoration:none;
      background:<?= $filter === $f ? 'var(--gold)' : '#1e1e1e' ?>;
      color:<?= $filter === $f ? '#000' : '#888' ?>;border:1px solid <?= $filter === $f ? 'var(--gold)' : '#2a2a2a' ?>">
                <?= $label ?>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="form-card" style="padding:0;overflow:hidden">
        <?php if (empty($messages)): ?>
            <div class="empty-state"><i class="fa-solid fa-envelope-open"></i>No messages <?= $filter !== 'all' ? "in this view" : "yet" ?>.</div>
        <?php else: ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th></th>
                        <th>From</th>
                        <th>Enquiry</th>
                        <th>Message</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($messages as $m): ?>
                        <tr style="<?= !$m['is_read'] ? 'background:rgba(201,168,76,.04)' : '' ?>">
                            <td>
                                <?php if (!$m['is_read']): ?>
                                    <span title="Unread" style="display:inline-block;width:8px;height:8px;border-radius:50%;background:var(--gold)"></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="?action=view&id=<?= (int)$m['id'] ?>" style="text-decoration:none">
                                    <strong style="color:#fff"><?= htmlspecialchars($m['name']) ?></strong><br>
                                    <small style="color:#555"><?= htmlspecialchars($m['email']) ?></small>
                                </a>
                            </td>
                            <td><span style="font-size:12px;background:#1a1a1a;padding:4px 10px;border-radius:100px;color:#bbb;white-space:nowrap"><?= htmlspecialchars($m['enquiry'] ?: 'General') ?></span></td>
                            <td style="max-width:260px"><a href="?action=view&id=<?= (int)$m['id'] ?>" style="color:#999;text-decoration:none;font-size:13px"><?= htmlspecialchars(msgExcerpt($m['message'])) ?></a></td>
                            <td><small style="white-space:nowrap"><?= date('d M Y', strtotime($m['created_at'])) ?><br><span style="color:#555"><?= date('g:i A', strtotime($m['created_at'])) ?></span></small></td>
                            <td style="white-space:nowrap">
                                <a href="?action=view&id=<?= (int)$m['id'] ?>" class="btn-sm-action" title="View" style="color:#888">
                                    <i class="fa-solid fa-eye"></i>
                                </a>
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="_action" value="toggle">
                                    <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                                    <input type="hidden" name="current" value="<?= (int)$m['is_read'] ?>">
                                    <button type="submit" class="btn-sm-action" title="<?= $m['is_read'] ? 'Mark unread' : 'Mark read' ?>" style="color:#888">
                                        <i class="fa-solid fa-<?= $m['is_read'] ? 'envelope' : 'envelope-open' ?>"></i>
                                    </button>
                                </form>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Delete this message? This cannot be undone.')">
                                    <input type="hidden" name="_action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                                    <button type="submit" class="btn-sm-action" title="Delete" style="color:#dc3545">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/_footer.php'; ?>
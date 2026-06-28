<?php
$pageTitle  = 'Tools Products';
$activePage = 'tools-products';
require_once __DIR__ . '/_layout.php';

$action  = $_GET['action'] ?? 'list';
$editId  = (int)($_GET['id'] ?? 0);
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['_action'] ?? '';

    if ($act === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $r  = $pdo->prepare("SELECT image FROM tools_products WHERE id=?");
        $r->execute([$id]);
        $old = $r->fetchColumn();
        if ($old && file_exists(APP_ROOT . '/uploads/' . $old)) @unlink(APP_ROOT . '/uploads/' . $old);
        $pdo->prepare("DELETE FROM tools_products WHERE id=?")->execute([$id]);
        header('Location: tools-products.php?success=' . urlencode('Product deleted.'));
        exit;
    }

    if ($act === 'toggle') {
        $id  = (int)($_POST['id'] ?? 0);
        $cur = (int)($_POST['current'] ?? 1);
        $pdo->prepare("UPDATE tools_products SET in_stock=? WHERE id=?")->execute([$cur ? 0 : 1, $id]);
        header('Location: tools-products.php?success=' . urlencode('Stock status updated.'));
        exit;
    }

    if (in_array($act, ['add', 'edit'])) {
        $name    = clean($_POST['name']        ?? '');
        $cat     = $_POST['category']          ?? 'styling';
        $price   = (float)($_POST['price']     ?? 0);
        $desc    = clean($_POST['description'] ?? '');
        $badge   = clean($_POST['badge']        ?? '');
        $specs   = clean($_POST['specs']        ?? '');
        $colors  = clean($_POST['colors']       ?? 'Default');
        $inStock = isset($_POST['in_stock']) ? 1 : 0;
        $sortOrd = (int)($_POST['sort_order']  ?? 0);

        if (!$name || $price <= 0 || !in_array($cat, ['styling', 'dryers', 'equipment', 'accessories'])) {
            $error = 'Name, valid category and price are required.';
        } else {
            $imagePath = clean($_POST['existing_image'] ?? '');
            if (!empty($_FILES['image']['name'])) {
                $ext     = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
                if (!in_array($ext, $allowed)) {
                    $error = 'Only JPG, PNG, WEBP, GIF images allowed.';
                } elseif ($_FILES['image']['size'] > 5 * 1024 * 1024) {
                    $error = 'Image must be under 5MB.';
                } else {
                    $newName = 'tools/tool_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    $dest    = APP_ROOT . '/uploads/' . $newName;
                    if (!is_dir(APP_ROOT . '/uploads/tools')) mkdir(APP_ROOT . '/uploads/tools', 0755, true);
                    if (move_uploaded_file($_FILES['image']['tmp_name'], $dest)) {
                        if ($imagePath && file_exists(APP_ROOT . '/uploads/' . $imagePath)) @unlink(APP_ROOT . '/uploads/' . $imagePath);
                        $imagePath = $newName;
                    } else {
                        $error = 'Failed to upload image.';
                    }
                }
            }

            if (!$error) {
                if ($act === 'add') {
                    $pdo->prepare(
                        "INSERT INTO tools_products (name,category,price,description,image,badge,specs,colors,in_stock,sort_order)
                         VALUES (?,?,?,?,?,?,?,?,?,?)"
                    )->execute([$name, $cat, $price, $desc, $imagePath, $badge, $specs, $colors, $inStock, $sortOrd]);
                } else {
                    $id = (int)($_POST['id'] ?? 0);
                    $pdo->prepare(
                        "UPDATE tools_products SET name=?,category=?,price=?,description=?,image=?,badge=?,specs=?,colors=?,in_stock=?,sort_order=? WHERE id=?"
                    )->execute([$name, $cat, $price, $desc, $imagePath, $badge, $specs, $colors, $inStock, $sortOrd, $id]);
                }
                header('Location: tools-products.php?success=' . urlencode($act === 'add' ? 'Product added!' : 'Product updated!'));
                exit;
            }
        }
    }
}

$editProduct = null;
if ($action === 'edit' && $editId) {
    $s = $pdo->prepare("SELECT * FROM tools_products WHERE id=?");
    $s->execute([$editId]);
    $editProduct = $s->fetch();
    if (!$editProduct) {
        header('Location: tools-products.php');
        exit;
    }
}

$filterCat = $_GET['cat'] ?? 'all';
if ($filterCat !== 'all') {
    $stmt = $pdo->prepare("SELECT * FROM tools_products WHERE category=? ORDER BY sort_order ASC, id DESC");
    $stmt->execute([$filterCat]);
} else {
    $stmt = $pdo->query("SELECT * FROM tools_products ORDER BY sort_order ASC, id DESC");
}
$products = $stmt->fetchAll();

$catLabels = ['styling' => 'Styling Tools', 'dryers' => 'Dryers', 'equipment' => 'Salon Equipment', 'accessories' => 'Accessories'];
function fmtT(float $n): string
{
    return '₦' . number_format($n, 0, '.', ',');
}
?>

<?php if ($action === 'add' || $action === 'edit'): ?>
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:24px">
        <a href="tools-products.php" style="color:#888;text-decoration:none;font-size:13px">
            <i class="fa-solid fa-arrow-left me-1"></i>Tools Products
        </a>
        <span style="color:#444">/</span>
        <span style="color:#fff;font-size:14px"><?= $action === 'add' ? 'Add Tool' : 'Edit Tool' ?></span>
    </div>

    <?php if ($error): ?><div style="background:#2a1515;border:1px solid #5a2a2a;color:#ff8a8a;border-radius:8px;padding:12px 16px;margin-bottom:20px;font-size:14px"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="_action" value="<?= $action ?>">
        <?php if ($editProduct): ?>
            <input type="hidden" name="id" value="<?= $editProduct['id'] ?>">
            <input type="hidden" name="existing_image" value="<?= htmlspecialchars($editProduct['image'] ?? '') ?>">
        <?php endif; ?>

        <div class="row g-3">
            <div class="col-lg-8">
                <div class="form-card">
                    <h5 style="color:#fff;margin-bottom:20px"><?= $action === 'add' ? 'New Tool Product' : 'Edit: ' . htmlspecialchars($editProduct['name']) ?></h5>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Product Name *</label>
                            <input type="text" name="name" class="form-control" required placeholder="e.g. Professional Hair Dryer 2400W"
                                value="<?= htmlspecialchars($editProduct['name'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Category *</label>
                            <select name="category" class="form-select">
                                <?php foreach ($catLabels as $k => $v): ?>
                                    <option value="<?= $k ?>" <?= ($editProduct['category'] ?? '') === $k ? 'selected' : '' ?>><?= $v ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Price (₦) *</label>
                            <input type="number" name="price" class="form-control" step="100" min="0" required
                                placeholder="15000" value="<?= $editProduct['price'] ?? '' ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3"
                                placeholder="Describe this tool..."><?= htmlspecialchars($editProduct['description'] ?? '') ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Specs / Features <small style="color:#555">(comma separated)</small></label>
                            <textarea name="specs" class="form-control" rows="2"
                                placeholder="2400W motor, Ionic technology, 3 heat settings"><?= htmlspecialchars($editProduct['specs'] ?? '') ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Colors / Variants <small style="color:#555">(comma separated)</small></label>
                            <input type="text" name="colors" class="form-control"
                                placeholder="Black,White,Rose Gold"
                                value="<?= htmlspecialchars($editProduct['colors'] ?? 'Default') ?>">
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="form-card mb-3">
                    <label class="form-label">Product Image</label>
                    <?php if (!empty($editProduct['image'])): ?>
                        <img src="../uploads/<?= htmlspecialchars($editProduct['image']) ?>" style="width:100%;border-radius:8px;margin-bottom:12px;object-fit:cover;max-height:180px">
                    <?php endif; ?>
                    <input type="file" name="image" class="form-control" accept="image/*">
                    <small style="color:#555;font-size:12px;margin-top:6px;display:block">JPG, PNG, WEBP - max 5MB</small>
                </div>
                <div class="form-card mb-3">
                    <label class="form-label">Badge Label <small style="color:#555">(optional)</small></label>
                    <input type="text" name="badge" class="form-control" placeholder="e.g. Professional, New Arrival"
                        value="<?= htmlspecialchars($editProduct['badge'] ?? '') ?>">
                    <div class="mt-3">
                        <label class="form-label">Sort Order</label>
                        <input type="number" name="sort_order" class="form-control" min="0"
                            value="<?= $editProduct['sort_order'] ?? 0 ?>">
                        <small style="color:#555;font-size:12px;margin-top:4px;display:block">Lower = appears first</small>
                    </div>
                </div>
                <div class="form-card">
                    <div class="form-check" style="display:flex;align-items:center;gap:10px">
                        <input type="checkbox" name="in_stock" id="inStock" class="form-check-input" style="width:18px;height:18px;border-radius:4px"
                            <?= ($editProduct['in_stock'] ?? 1) ? 'checked' : '' ?>>
                        <label for="inStock" class="form-label mb-0">In Stock</label>
                    </div>
                    <div style="margin-top:20px;display:flex;gap:10px">
                        <button type="submit" class="btn-gold flex-fill">
                            <i class="fa-solid fa-floppy-disk me-1"></i><?= $action === 'add' ? 'Add Tool' : 'Save Changes' ?>
                        </button>
                        <a href="tools-products.php" style="background:#222;color:#888;border:1px solid #333;border-radius:8px;padding:11px 16px;text-decoration:none;font-size:14px">Cancel</a>
                    </div>
                </div>
            </div>
        </div>
    </form>

<?php else: ?>
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:20px">
        <div>
            <div class="section-title">Tools & Equipment</div>
            <div class="section-sub"><?= count($products) ?> products total</div>
        </div>
        <a href="?action=add" class="btn-gold"><i class="fa-solid fa-plus me-1"></i>Add Tool</a>
    </div>

    <div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap">
        <?php foreach (array_merge(['all' => 'All'], $catLabels) as $k => $v): ?>
            <a href="?cat=<?= $k ?>" style="padding:6px 14px;border-radius:100px;font-size:13px;text-decoration:none;
      background:<?= $filterCat === $k ? 'var(--gold)' : '#1e1e1e' ?>;
      color:<?= $filterCat === $k ? '#000' : '#888' ?>;border:1px solid <?= $filterCat === $k ? 'var(--gold)' : '#2a2a2a' ?>">
                <?= $v ?>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="form-card" style="padding:0;overflow:hidden">
        <?php if (empty($products)): ?>
            <div class="empty-state"><i class="fa-solid fa-toolbox"></i>No tools yet. <a href="?action=add" style="color:var(--gold)">Add one →</a></div>
        <?php else: ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Image</th>
                        <th>Product</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $p): ?>
                        <tr>
                            <td>
                                <?php if (!empty($p['image'])): ?>
                                    <img src="../uploads/<?= htmlspecialchars($p['image']) ?>" class="img-thumb" alt="">
                                <?php else: ?>
                                    <div class="img-thumb" style="display:flex;align-items:center;justify-content:center;background:#1a1a1a"><i class="fa-solid fa-image" style="color:#333"></i></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong style="color:#fff"><?= htmlspecialchars($p['name']) ?></strong>
                                <?php if ($p['badge']): ?><br><span style="font-size:11px;color:var(--gold)"><?= htmlspecialchars($p['badge']) ?></span><?php endif; ?>
                            </td>
                            <td><span style="font-size:12px;background:#1a1a1a;padding:4px 10px;border-radius:100px;color:#bbb"><?= $catLabels[$p['category']] ?? ucfirst($p['category']) ?></span></td>
                            <td style="font-weight:700;color:var(--gold)"><?= fmtT((float)$p['price']) ?></td>
                            <td>
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="_action" value="toggle">
                                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                    <input type="hidden" name="current" value="<?= $p['in_stock'] ?>">
                                    <button type="submit" style="background:none;border:none;cursor:pointer;padding:0">
                                        <span style="display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:100px;font-size:11px;font-weight:600;
                background:<?= $p['in_stock'] ? 'rgba(40,167,69,.12)' : 'rgba(220,53,69,.12)' ?>;
                color:<?= $p['in_stock'] ? '#28a745' : '#dc3545' ?>">
                                            <i class="fa-solid fa-<?= $p['in_stock'] ? 'check' : 'xmark' ?>"></i>
                                            <?= $p['in_stock'] ? 'In Stock' : 'Out of Stock' ?>
                                        </span>
                                    </button>
                                </form>
                            </td>
                            <td>
                                <a href="?action=edit&id=<?= $p['id'] ?>" class="btn-sm-action" title="Edit" style="color:#888"><i class="fa-solid fa-pen-to-square"></i></a>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Delete this product?')">
                                    <input type="hidden" name="_action" value="delete">
                                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                    <button type="submit" class="btn-sm-action" style="color:#dc3545"><i class="fa-solid fa-trash"></i></button>
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
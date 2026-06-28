<?php
$pageTitle  = 'Luxury Hair Products';
$activePage = 'lux-products';
require_once __DIR__ . '/_layout.php';
require_once APP_ROOT . '/config/upload.php';

$action = $_GET['action'] ?? 'list';
$editId = (int)($_GET['id'] ?? 0);
$error  = '';

// ---------------------------------------------------------------
// Handle POST (add / edit / delete / toggle)
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['_action'] ?? '';

    // ---- DELETE ----
    if ($act === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $row = $pdo->prepare("SELECT image FROM luxury_products WHERE id=?");
            $row->execute([$id]);
            $old = $row->fetchColumn();
            if ($old) deleteProductImage($old);
            $pdo->prepare("DELETE FROM luxury_products WHERE id=?")->execute([$id]);
            header('Location: luxury-products.php?success=' . urlencode('Product deleted.'));
        } else {
            header('Location: luxury-products.php?error=' . urlencode('Invalid product.'));
        }
        exit;
    }

    // ---- TOGGLE STOCK ----
    if ($act === 'toggle') {
        $id  = (int)($_POST['id'] ?? 0);
        $cur = (int)($_POST['current'] ?? 1);
        if ($id > 0) {
            $pdo->prepare("UPDATE luxury_products SET in_stock=? WHERE id=?")->execute([$cur ? 0 : 1, $id]);
            header('Location: luxury-products.php?success=' . urlencode('Stock status updated.'));
        } else {
            header('Location: luxury-products.php?error=' . urlencode('Invalid product.'));
        }
        exit;
    }

    // ---- ADD / EDIT ----
    if (in_array($act, ['add', 'edit'], true)) {
        $name     = clean($_POST['name']        ?? '');
        $cat      = $_POST['category']          ?? 'extensions';
        $price    = (float)($_POST['price']     ?? 0);
        $desc     = clean($_POST['description'] ?? '');
        $badge    = clean($_POST['badge']        ?? '');
        $lengths  = clean($_POST['lengths']      ?? '');
        $textures = clean($_POST['textures']     ?? '');
        $inStock  = isset($_POST['in_stock']) ? 1 : 0;
        $sortOrd  = (int)($_POST['sort_order']  ?? 0);
        $allowedCats = ['extensions', 'wigs', 'bundles', 'closures'];

        if (!$name) {
            $error = 'Product name is required.';
        } elseif ($price <= 0) {
            $error = 'Price must be greater than 0.';
        } elseif (!in_array($cat, $allowedCats, true)) {
            $error = 'Please select a valid category.';
        }

        $existingImage = clean($_POST['existing_image'] ?? '');
        $imagePath     = $existingImage;

        if (!$error) {
            $upload = handleProductImageUpload($_FILES['image'] ?? [], 'products', 'lux', $existingImage);
            if (!$upload['ok']) {
                $error = $upload['error'];
            } elseif ($upload['path'] !== null) {
                $imagePath = $upload['path'];
            }
        }

        if (!$error) {
            try {
                if ($act === 'add') {
                    $pdo->prepare(
                        "INSERT INTO luxury_products (name,category,price,description,image,badge,lengths,textures,in_stock,sort_order)
                         VALUES (?,?,?,?,?,?,?,?,?,?)"
                    )->execute([$name, $cat, $price, $desc, $imagePath, $badge, $lengths, $textures, $inStock, $sortOrd]);
                    $okMsg = 'Product added!';
                } else {
                    $id = (int)($_POST['id'] ?? 0);
                    if ($id <= 0) throw new RuntimeException('Invalid product ID.');
                    $pdo->prepare(
                        "UPDATE luxury_products SET name=?,category=?,price=?,description=?,image=?,badge=?,lengths=?,textures=?,in_stock=?,sort_order=? WHERE id=?"
                    )->execute([$name, $cat, $price, $desc, $imagePath, $badge, $lengths, $textures, $inStock, $sortOrd, $id]);
                    $okMsg = 'Product updated!';
                }
                header('Location: luxury-products.php?success=' . urlencode($okMsg));
                exit;
            } catch (Exception $e) {
                $error = 'Database error: could not save product. Please try again.';
            }
        }
    }
}

// ---------------------------------------------------------------
// Load for edit
// ---------------------------------------------------------------
$editProduct = null;
if ($action === 'edit' && $editId) {
    $s = $pdo->prepare("SELECT * FROM luxury_products WHERE id=?");
    $s->execute([$editId]);
    $editProduct = $s->fetch();
    if (!$editProduct) {
        header('Location: luxury-products.php?error=' . urlencode('Product not found.'));
        exit;
    }
}

// ---------------------------------------------------------------
// Load list
// ---------------------------------------------------------------
$filterCat = $_GET['cat'] ?? 'all';
$validCats = ['all', 'extensions', 'wigs', 'bundles', 'closures'];
if (!in_array($filterCat, $validCats, true)) $filterCat = 'all';

if ($filterCat !== 'all') {
    $stmt = $pdo->prepare("SELECT * FROM luxury_products WHERE category=? ORDER BY sort_order ASC, id DESC");
    $stmt->execute([$filterCat]);
} else {
    $stmt = $pdo->query("SELECT * FROM luxury_products ORDER BY sort_order ASC, id DESC");
}
$products = $stmt->fetchAll();

function luxCatLabel(string $c): string
{
    return ['extensions' => 'Extensions', 'wigs' => 'Wigs', 'bundles' => 'Bundles', 'closures' => 'Closures'][$c] ?? ucfirst($c);
}
function fmtN2(float $n): string
{
    return '₦' . number_format($n, 0, '.', ',');
}
?>

<?php if ($action === 'add' || $action === 'edit'): ?>
    <!-- ===== ADD / EDIT FORM ===== -->
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:24px">
        <a href="luxury-products.php" style="color:#888;text-decoration:none;font-size:13px">
            <i class="fa-solid fa-arrow-left me-1"></i>Products
        </a>
        <span style="color:#444">/</span>
        <span style="color:#fff;font-size:14px"><?= $action === 'add' ? 'Add Product' : 'Edit Product' ?></span>
    </div>

    <?php if ($error): ?>
        <div style="background:#2a1515;border:1px solid #5a2a2a;color:#ff8a8a;border-radius:8px;padding:12px 16px;margin-bottom:20px;font-size:14px">
            <i class="fa-solid fa-circle-exclamation me-2"></i><?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="_action" value="<?= htmlspecialchars($action) ?>">
        <?php if ($editProduct): ?>
            <input type="hidden" name="id" value="<?= (int)$editProduct['id'] ?>">
            <input type="hidden" name="existing_image" value="<?= htmlspecialchars($editProduct['image'] ?? '') ?>">
        <?php endif; ?>

        <div class="row g-3">
            <div class="col-lg-8">
                <div class="form-card">
                    <h5 style="color:#fff;margin-bottom:20px">
                        <?= $action === 'add' ? 'New Hair Product' : 'Edit: ' . htmlspecialchars($editProduct['name']) ?>
                    </h5>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Product Name *</label>
                            <input type="text" name="name" class="form-control" required placeholder="e.g. Brazilian Body Wave Bundle"
                                value="<?= htmlspecialchars($_POST['name'] ?? $editProduct['name'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Category *</label>
                            <select name="category" class="form-select">
                                <?php
                                $selCat = $_POST['category'] ?? $editProduct['category'] ?? '';
                                foreach (['extensions', 'wigs', 'bundles', 'closures'] as $c): ?>
                                    <option value="<?= $c ?>" <?= $selCat === $c ? 'selected' : '' ?>><?= luxCatLabel($c) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Price (₦) *</label>
                            <input type="number" name="price" class="form-control" step="100" min="0" required
                                placeholder="25000" value="<?= htmlspecialchars($_POST['price'] ?? $editProduct['price'] ?? '') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3"
                                placeholder="Describe the product..."><?= htmlspecialchars($_POST['description'] ?? $editProduct['description'] ?? '') ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Available Lengths <small style="color:#555">(comma separated)</small></label>
                            <input type="text" name="lengths" class="form-control"
                                placeholder="12 inch,14 inch,16 inch,18 inch,20 inch"
                                value="<?= htmlspecialchars($_POST['lengths'] ?? $editProduct['lengths'] ?? '12 inch,14 inch,16 inch') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Textures <small style="color:#555">(comma separated)</small></label>
                            <input type="text" name="textures" class="form-control"
                                placeholder="Straight,Body Wave,Deep Wave,Curly"
                                value="<?= htmlspecialchars($_POST['textures'] ?? $editProduct['textures'] ?? 'Straight,Body Wave') ?>">
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="form-card mb-3">
                    <label class="form-label">Product Image</label>
                    <?php if (!empty($editProduct['image'])): ?>
                        <img src="<?= htmlspecialchars('../uploads/' . $editProduct['image']) ?>" style="width:100%;border-radius:8px;margin-bottom:12px;object-fit:cover;max-height:180px">
                    <?php endif; ?>
                    <input type="file" name="image" class="form-control" accept="image/jpeg,image/png,image/webp,image/gif">
                    <small style="color:#555;font-size:12px;margin-top:6px;display:block">JPG, PNG, WEBP, GIF - max 5MB</small>
                </div>
                <div class="form-card mb-3">
                    <label class="form-label">Badge Label <small style="color:#555">(optional)</small></label>
                    <input type="text" name="badge" class="form-control" placeholder="e.g. Bestseller, New"
                        value="<?= htmlspecialchars($_POST['badge'] ?? $editProduct['badge'] ?? '') ?>">
                    <div class="mt-3">
                        <label class="form-label">Sort Order</label>
                        <input type="number" name="sort_order" class="form-control" min="0"
                            value="<?= htmlspecialchars($_POST['sort_order'] ?? $editProduct['sort_order'] ?? 0) ?>">
                        <small style="color:#555;font-size:12px;margin-top:4px;display:block">Lower = appears first</small>
                    </div>
                </div>
                <div class="form-card">
                    <div class="form-check" style="display:flex;align-items:center;gap:10px">
                        <?php
                        $checkedIn = isset($_POST['_action']) ? isset($_POST['in_stock']) : (bool)($editProduct['in_stock'] ?? 1);
                        ?>
                        <input type="checkbox" name="in_stock" id="inStock" class="form-check-input" style="width:18px;height:18px;border-radius:4px"
                            <?= $checkedIn ? 'checked' : '' ?>>
                        <label for="inStock" class="form-label mb-0">In Stock</label>
                    </div>
                    <div style="margin-top:20px;display:flex;gap:10px">
                        <button type="submit" class="btn-gold flex-fill">
                            <i class="fa-solid fa-floppy-disk me-1"></i><?= $action === 'add' ? 'Add Product' : 'Save Changes' ?>
                        </button>
                        <a href="luxury-products.php" style="background:#222;color:#888;border:1px solid #333;border-radius:8px;padding:11px 16px;text-decoration:none;font-size:14px">Cancel</a>
                    </div>
                </div>
            </div>
        </div>
    </form>

<?php else: ?>
    <!-- ===== PRODUCT LIST ===== -->
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:20px">
        <div>
            <div class="section-title">Hair Products</div>
            <div class="section-sub"><?= count($products) ?> products total</div>
        </div>
        <a href="?action=add" class="btn-gold"><i class="fa-solid fa-plus me-1"></i>Add Product</a>
    </div>

    <div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap">
        <?php foreach (['all', 'extensions', 'wigs', 'bundles', 'closures'] as $c): ?>
            <a href="?cat=<?= $c ?>" style="padding:6px 14px;border-radius:100px;font-size:13px;text-decoration:none;
      background:<?= $filterCat === $c ? 'var(--gold)' : '#1e1e1e' ?>;
      color:<?= $filterCat === $c ? '#000' : '#888' ?>;border:1px solid <?= $filterCat === $c ? 'var(--gold)' : '#2a2a2a' ?>">
                <?= $c === 'all' ? 'All' : luxCatLabel($c) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="form-card" style="padding:0;overflow:hidden">
        <?php if (empty($products)): ?>
            <div class="empty-state"><i class="fa-solid fa-box-open"></i>No products yet. <a href="?action=add" style="color:var(--gold)">Add one →</a></div>
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
                                <?php if (!empty($p['image']) && is_file(APP_ROOT . '/uploads/' . $p['image'])): ?>
                                    <img src="../uploads/<?= htmlspecialchars($p['image']) ?>" class="img-thumb" alt="">
                                <?php else: ?>
                                    <div class="img-thumb" style="display:flex;align-items:center;justify-content:center;background:#1a1a1a">
                                        <i class="fa-solid fa-image" style="color:#333"></i>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong style="color:#fff"><?= htmlspecialchars($p['name']) ?></strong>
                                <?php if ($p['badge']): ?><br><span style="font-size:11px;color:var(--gold)"><?= htmlspecialchars($p['badge']) ?></span><?php endif; ?>
                            </td>
                            <td><span style="font-size:12px;background:#1a1a1a;padding:4px 10px;border-radius:100px;color:#bbb"><?= luxCatLabel($p['category']) ?></span></td>
                            <td style="font-weight:700;color:var(--gold)"><?= fmtN2((float)$p['price']) ?></td>
                            <td>
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="_action" value="toggle">
                                    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                    <input type="hidden" name="current" value="<?= (int)$p['in_stock'] ?>">
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
                                <a href="?action=edit&id=<?= (int)$p['id'] ?>" class="btn-sm-action" title="Edit" style="color:#888">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </a>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Delete this product? This cannot be undone.')">
                                    <input type="hidden" name="_action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
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
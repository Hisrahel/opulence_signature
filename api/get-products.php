<?php
// api/get-products.php - Returns luxury hair products as JSON

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config/db.php';

try {
    $pdo  = getDB();
    $cat  = $_GET['category'] ?? 'all';

    if ($cat !== 'all' && in_array($cat, ['extensions', 'wigs', 'bundles', 'closures'])) {
        $stmt = $pdo->prepare('SELECT * FROM luxury_products WHERE in_stock = 1 AND category = ? ORDER BY sort_order ASC, id DESC');
        $stmt->execute([$cat]);
    } else {
        $stmt = $pdo->query('SELECT * FROM luxury_products WHERE in_stock = 1 ORDER BY sort_order ASC, id DESC');
    }

    $products = $stmt->fetchAll();

    // Normalize image path
    foreach ($products as &$p) {
        if (!empty($p['image']) && !str_starts_with($p['image'], 'http')) {
            $p['image'] = '../uploads/' . ltrim($p['image'], '/');
        }
        // Ensure empty image fallback
        if (empty($p['image'])) {
            $p['image'] = '../images/extension.webp';
        }
    }
    unset($p);

    echo json_encode($products);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to load products.']);
}

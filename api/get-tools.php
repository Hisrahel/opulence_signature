<?php
// api/get-tools.php - Returns tools products as JSON

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config/db.php';

try {
    $pdo = getDB();
    $cat = $_GET['category'] ?? 'all';

    if ($cat !== 'all' && in_array($cat, ['styling', 'dryers', 'equipment', 'accessories'])) {
        $stmt = $pdo->prepare('SELECT * FROM tools_products WHERE category = ? ORDER BY sort_order ASC, id DESC');
        $stmt->execute([$cat]);
    } else {
        $stmt = $pdo->query('SELECT * FROM tools_products ORDER BY sort_order ASC, id DESC');
    }

    $products = $stmt->fetchAll();

    foreach ($products as &$p) {
        if (!empty($p['image']) && !str_starts_with($p['image'], 'http')) {
            $p['image'] = '../uploads/' . ltrim($p['image'], '/');
        }
        if (empty($p['image'])) {
            $p['image'] = '../images/treatment.png';
        }
        // Normalize in_stock
        $p['in_stock'] = (bool)$p['in_stock'];
    }
    unset($p);

    echo json_encode($products);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to load tools.']);
}

<?php
// api/place-order.php - Handles luxury hair orders

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

require_once __DIR__ . '/../config/db.php';

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid payload.']);
    exit;
}

$name    = clean($data['name']    ?? '');
$phone   = clean($data['phone']   ?? '');
$email   = clean($data['email']   ?? '');
$address = clean($data['address'] ?? '');
$notes   = clean($data['notes']   ?? '');
$items   = $data['items'] ?? [];
$type    = ($data['type'] ?? 'luxury') === 'tools' ? 'tools' : 'luxury';

if (!$name || !$phone || !$address || empty($items)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Name, phone, address and items are required.']);
    exit;
}

// Validate and price items from DB
$pdo   = getDB();
$table = $type === 'tools' ? 'tools_products' : 'luxury_products';

$subtotal    = 0;
$validItems  = [];

foreach ($items as $item) {
    $id  = (int)($item['id'] ?? 0);
    $qty = max(1, (int)($item['qty'] ?? 1));

    $stmt = $pdo->prepare("SELECT id, name, price FROM $table WHERE id = ?");
    $stmt->execute([$id]);
    $product = $stmt->fetch();

    if (!$product) continue;

    $lineTotal  = $product['price'] * $qty;
    $subtotal  += $lineTotal;

    $validItems[] = [
        'id'       => $product['id'],
        'name'     => $product['name'],
        'price'    => (float)$product['price'],
        'qty'      => $qty,
        'subtotal' => $lineTotal,
        'length'   => clean($item['length']  ?? ''),
        'texture'  => clean($item['texture'] ?? ''),
        'color'    => clean($item['color']   ?? ''),
    ];
}

if (empty($validItems)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'No valid products in order.']);
    exit;
}

// Settings
$stmt    = $pdo->query("SELECT setting_key, setting_value FROM site_settings WHERE setting_key IN ('free_delivery_threshold','delivery_fee')");
$settings = [];
foreach ($stmt->fetchAll() as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$threshold   = (float)($settings['free_delivery_threshold'] ?? 50000);
$deliveryFee = $subtotal >= $threshold ? 0 : (float)($settings['delivery_fee'] ?? 2500);
$total       = $subtotal + $deliveryFee;

$ref = generateRef($type === 'tools' ? 'OPT' : 'OPL');

$orderTable = $type === 'tools' ? 'tools_orders' : 'luxury_orders';

$stmt = $pdo->prepare(
    "INSERT INTO $orderTable
     (order_ref, customer_name, customer_phone, customer_email, delivery_address,
      notes, items_json, subtotal, delivery_fee, total)
     VALUES (?,?,?,?,?,?,?,?,?,?)"
);
$stmt->execute([
    $ref,
    $name,
    $phone,
    $email,
    $address,
    $notes,
    json_encode($validItems),
    $subtotal,
    $deliveryFee,
    $total,
]);

echo json_encode([
    'success'    => true,
    'order_ref'  => $ref,
    'subtotal'   => $subtotal,
    'delivery'   => $deliveryFee,
    'total'      => $total,
    'message'    => 'Order placed successfully.',
]);

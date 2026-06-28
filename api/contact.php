<?php
// api/contact.php - Saves contact form messages

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
    echo json_encode(['success' => false]);
    exit;
}

require_once __DIR__ . '/../config/db.php';

$data    = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$name    = clean($data['name']    ?? '');
$email   = filter_var(trim($data['email'] ?? ''), FILTER_SANITIZE_EMAIL);
$phone   = clean($data['phone']   ?? '');
$enquiry = clean($data['enquiry'] ?? 'General Enquiry');
$message = clean($data['message'] ?? '');

if (!$name || !$email || !$message) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Name, email, and message are required.']);
    exit;
}

try {
    $pdo  = getDB();
    $stmt = $pdo->prepare(
        'INSERT INTO contact_messages (name, email, phone, enquiry, message) VALUES (?,?,?,?,?)'
    );
    $stmt->execute([$name, $email, $phone, $enquiry, $message]);
    echo json_encode(['success' => true, 'message' => 'Message received. We will get back to you shortly.']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to send message.']);
}

<?php
// api/bookings.php - Handles appointment booking submissions

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
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload.']);
    exit;
}

// Validate required fields
$required = ['service', 'appointment_date', 'appointment_time', 'fullname', 'email', 'phone'];
foreach ($required as $field) {
    if (empty(trim($data[$field] ?? ''))) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => "Field '$field' is required."]);
        exit;
    }
}

// Validate date
$dateStr = trim($data['appointment_date']);
$dateObj = DateTime::createFromFormat('Y-m-d', $dateStr);
if (!$dateObj || $dateStr < date('Y-m-d')) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid or past appointment date.']);
    exit;
}

// Validate phone
$phone = preg_replace('/[^\d+]/', '', trim($data['phone']));
if (strlen($phone) < 10) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid phone number.']);
    exit;
}

// Allowed values
$allowedServices = [
    'Wig Installation & Styling',
    'Hair Treatments & Restoration',
    'Bridal & Special Occasion',
    'Hair Maintenance & Revamp',
    'Hair Extension Installation',
    'Consultation',
];
$allowedLocations = ['Lagos', 'Osogbo'];
$allowedContacts  = ['WhatsApp', 'Phone Call', 'Email'];
$allowedDurations = ['1hr', '2hrs', '3hrs', '4hrs+'];
$allowedTimes     = ['9:00 AM', '10:00 AM', '11:00 AM', '12:00 PM', '1:00 PM', '2:00 PM', '3:00 PM', '4:00 PM', '5:00 PM', '6:00 PM'];

$service  = in_array($data['service'], $allowedServices) ? $data['service'] : '';
$location = in_array($data['location'] ?? 'Lagos', $allowedLocations) ? $data['location'] : 'Lagos';
$contact  = in_array($data['preferred_contact'] ?? 'WhatsApp', $allowedContacts) ? $data['preferred_contact'] : 'WhatsApp';
$duration = in_array($data['duration'] ?? '2hrs', $allowedDurations) ? $data['duration'] : '2hrs';
$time     = in_array($data['appointment_time'], $allowedTimes) ? $data['appointment_time'] : '';

if (!$service || !$time) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid service or time slot.']);
    exit;
}

try {
    $pdo = getDB();

    // Check for conflicting booking at same date/time/location
    $conflict = $pdo->prepare(
        'SELECT id FROM saloon_bookings
         WHERE appointment_date = ? AND appointment_time = ? AND location = ?
         AND status NOT IN ("cancelled","no_show")
         LIMIT 1'
    );
    $conflict->execute([$dateStr, $time, $location]);
    if ($conflict->fetch()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'That time slot is already booked. Please choose another time.']);
        exit;
    }

    $ref = generateRef('OPS');

    $stmt = $pdo->prepare(
        'INSERT INTO saloon_bookings
         (booking_ref, service, location, appointment_date, appointment_time, duration,
          fullname, email, phone, preferred_contact, notes)
         VALUES (?,?,?,?,?,?,?,?,?,?,?)'
    );
    $stmt->execute([
        $ref,
        $service,
        $location,
        $dateStr,
        $time,
        $duration,
        clean($data['fullname']),
        filter_var(trim($data['email']), FILTER_SANITIZE_EMAIL),
        $phone,
        $contact,
        clean($data['notes'] ?? ''),
    ]);

    echo json_encode([
        'success'     => true,
        'message'     => 'Booking received successfully.',
        'booking_ref' => $ref,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error. Please try again.']);
}

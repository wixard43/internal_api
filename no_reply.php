<?php
error_reporting(0);

$crmConfig = [
    'wow' => [
        'db'    => 'wazzup_wow',
        'query' => "SELECT id as phone, last_message FROM contacts
                    WHERE notifications > 0 AND platform = 'whatsapp'
                    AND last_message BETWEEN ? AND ?",
    ],
    'curvy' => [
        'db'    => 'wazzup_curvy',
        'query' => "SELECT id as phone, last_message FROM contacts
                     WHERE notifications > 0 AND platform = 'whatsapp'
                     AND last_message BETWEEN ? AND ?",
    ],
    'dds' => [
        'db'    => 'wazzup_dds',
        'query' => "SELECT id as phone, last_message FROM contacts
                     WHERE notifications > 0 AND platform = 'whatsapp'
                     AND last_message BETWEEN ? AND ?",
    ],
    'daso' => [
        'db'    => 'wazzup_daso',
        'query' => "SELECT id as phone, last_message FROM contacts
                     WHERE notifications > 0 AND notifications_admin > 0
                     AND last_message BETWEEN ? AND ?",
    ],
    'ecl' => [
        'db'    => 'wazzup_ecl',
        'query' => "SELECT id as phone, last_message FROM contacts
                     WHERE notifications > 0 AND platform = 'whatsapp'
                     AND last_message BETWEEN ? AND ?",
    ],
];

$crm = $_GET['crm'] ?? null;
if (!$crm || !isset($crmConfig[$crm])) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["error" => "Missing or invalid crm parameter. Valid values: " . implode(', ', array_keys($crmConfig))]);
    exit;
}

$dateFrom = $_GET['date_from'] ?? null;
$dateTo   = $_GET['date_to']   ?? null;

if (!$dateFrom || !$dateTo) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["error" => "Missing date_from or date_to parameters (format: YYYY-MM-DD)"]);
    exit;
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["error" => "Invalid date format. Expected YYYY-MM-DD"]);
    exit;
}

$dtFrom = $dateFrom . ' 00:00:00';
$dtTo   = $dateTo   . ' 23:59:59';

$ini_db = parse_ini_file(__DIR__ . '/app.ini');
$conn = new mysqli($ini_db['servername'], $ini_db['db_user'], $ini_db['db_password'], $crmConfig[$crm]['db']);
if ($conn->connect_error) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["error" => "DB connection failed"]);
    exit;
}
$conn->set_charset('utf8mb4');

$stmt = $conn->prepare($crmConfig[$crm]['query']);
$stmt->bind_param('ss', $dtFrom, $dtTo);
$stmt->execute();
$result = $stmt->get_result();

$contacts = [];
while ($row = $result->fetch_assoc()) {
    $contacts[] = $row;
}

$stmt->close();
$conn->close();

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'count'    => count($contacts),
    'contacts' => $contacts,
]);
exit;

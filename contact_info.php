<?php
error_reporting(0);

$crmConfig = [
    'wow' => [
        'db'    => 'wazzup_wow',
        'query' => "SELECT m.direction, m.timestamp
                     FROM message m
                     WHERE m.contact = ?
                     ORDER BY m.timestamp ASC",
    ],
    'curvy' => [
        'db'    => 'wazzup_curvy',
        'query' => "SELECT m.direction, m.timestamp
                     FROM message m
                     WHERE m.contact = ?
                     ORDER BY m.timestamp ASC",
    ],
    'dds' => [
        'db'    => 'wazzup_dds',
        'query' => "SELECT m.direction, m.timestamp
                     FROM message m
                     WHERE m.contact = ?
                     ORDER BY m.timestamp ASC",
    ],
    'daso' => [
        'db'    => 'wazzup_daso',
        'query' => "SELECT m.direction, m.timestamp
                     FROM message m
                     WHERE m.contact = ?
                     ORDER BY m.timestamp ASC",
    ],
    'ecl' => [
        'db'    => 'wazzup_ecl',
        'query' => "SELECT m.direction, m.timestamp
                     FROM message m
                     WHERE m.contact = ?
                     ORDER BY m.timestamp ASC",
    ],

];

$crm = $_GET['crm'] ?? null;
if (!$crm || !isset($crmConfig[$crm])) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["error" => "Missing or invalid crm parameter. Valid values: " . implode(', ', array_keys($crmConfig))]);
    exit;
}

$phone = $_GET['phone'] ?? null;
if ($phone === null || trim($phone) === '') {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["error" => "Missing phone parameter"]);
    exit;
}

$contact = ltrim(trim($phone), '+');

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
$stmt->bind_param('s', $contact);
$stmt->execute();
$result = $stmt->get_result();

$byDate = [];
while ($msg = $result->fetch_assoc()) {
    if (empty($msg['timestamp'])) continue;
    $date = substr($msg['timestamp'], 0, 10);
    if (!isset($byDate[$date])) {
        $byDate[$date] = ['agent_msg_count' => 0, 'client_replied' => false];
    }
    if ($msg['direction'] === 'outgoing') {
        $byDate[$date]['agent_msg_count']++;
    } elseif ($msg['direction'] === 'incoming') {
        $byDate[$date]['client_replied'] = true;
    }
}

krsort($byDate);

$messages = [];
foreach ($byDate as $date => $data) {
    $messages[] = [
        'date'            => $date,
        'agent_msg_count' => $data['agent_msg_count'],
        'client_replied'  => $data['client_replied'],
    ];
}

$stmt->close();
$conn->close();

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'has_chat' => count($messages) > 0,
    'messages' => $messages,
]);
exit;

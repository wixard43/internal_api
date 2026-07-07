<?php
require __DIR__ . '/vendor/autoload.php';

header('Content-Type: application/json');

// ─── CRM config ───────────────────────────────────────────────────────────────
$crmConfig = [
    'daso' => [
        'prefix'       => 'missed_call_daso',
        'contact_base' => 'https://daso.dds.miami/crm/',
    ],
    'dds' => [
        'prefix'       => 'missed_call_dds',
        'contact_base' => 'https://dds.miami/crm/',
    ],
    'wow' => [
        'prefix'       => 'missed_call_wow',
        'contact_base' => 'https://btx.wowdentaldesigns.com/crm/',
    ],
    'curvy' => [
        'prefix'       => 'missed_call_curvy',
        'contact_base' => 'https://btx.curvyplasticsurgery.com/crm/',
    ],
    'ecl' => [
        'prefix'       => 'missed_call_ecl',
        'contact_base' => 'https://crm.eyescolorlab.com/crm/',
    ],
];

$crm = $_GET['crm'] ?? null;
if (!$crm || !isset($crmConfig[$crm])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing or invalid crm parameter. Valid values: ' . implode(', ', array_keys($crmConfig))]);
    exit;
}

$PREFIX       = $crmConfig[$crm]['prefix'];
$CONTACT_BASE = $crmConfig[$crm]['contact_base'];
$INITIAL_TTL  = 604800;

// ─── Redis ────────────────────────────────────────────────────────────────────
$redis = new Predis\Client();

$calls = [];
$keys  = $redis->keys($PREFIX . ':*');

foreach ($keys as $key) {
    if (strpos($key, ':phone:') !== false || strpos($key, ':callback:') !== false) continue;
    $data = $redis->hgetall($key);
    if (empty($data)) continue;
    if (strtolower($data['phone'] ?? '') === 'nonymous') continue;

    $ttl                = $redis->ttl($key);
    $data['_ttl']       = $ttl;
    $data['_call_time'] = ($ttl > 0) ? (time() - ($INITIAL_TTL - $ttl)) : null;
    $calls[]            = $data;
}

// Ordenar más reciente primero
usort($calls, function ($a, $b) {
    $ta = ($a['_ttl'] ?? -1) < 0 ? -1 : ($a['_ttl'] ?? -1);
    $tb = ($b['_ttl'] ?? -1) < 0 ? -1 : ($b['_ttl'] ?? -1);
    return $tb <=> $ta;
});

// Contar por entity_id
$entityCount = [];
foreach ($calls as $c) {
    $id = $c['entity_id'] ?? '';
    if ($id !== '') $entityCount[$id] = ($entityCount[$id] ?? 0) + 1;
}

// Deduplicar por entity_id
$seen  = [];
$calls = array_values(array_filter($calls, function ($c) use (&$seen) {
    $id = $c['entity_id'] ?? '';
    if ($id === '' || isset($seen[$id])) return false;
    $seen[$id] = true;
    return true;
}));

// ─── Construir respuesta ──────────────────────────────────────────────────────
$result = [];
foreach ($calls as $c) {
    $entity_type = strtolower($c['entity_type'] ?? '');
    $entity_id   = $c['entity_id'] ?? '';
    if ($entity_id === '') continue;

    $dt = null;
    if ($c['_call_time'] !== null) {
        $dt = (new DateTime('@' . $c['_call_time']))->setTimezone(new DateTimeZone('America/New_York'));
    }

    $result[] = [
        'entity_type'    => $entity_type,
        'entity_id'      => $entity_id,
        'responsible_id' => $c['responsible_id'] ?? null,
        'contact_link'   => $CONTACT_BASE . $entity_type . '/details/' . $entity_id . '/',
        'status'         => !empty($c['callback_ids']) ? 'callback_no_answer' : 'missing',
        'date_time'      => $dt ? $dt->format('Y-m-d H:i:s') : null,
        'cantidad'       => $entityCount[$entity_id] ?? 1,
    ];
}

echo json_encode(['data' => $result], JSON_PRETTY_PRINT);

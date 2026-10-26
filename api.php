<?php
require __DIR__ . '/lib.php';

$holder = current_holder();
$person = $holder['id'] ? people()[$holder['id']] : null;

header('Content-Type: application/json');
header('Cache-Control: no-store');
echo json_encode([
    'id' => $holder['id'],
    'name' => $person['name'] ?? null,
    'icon' => $person['icon'] ?? null,
    'since' => $holder['since'],
]);

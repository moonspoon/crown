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
    // Everyone who can hold the crown, so clients (the Android app) can ask who they are.
    'people' => array_map(fn($id, $p) => ['id' => $id, 'name' => $p['name'], 'icon' => $p['icon']], array_keys(people()), people()),
]);

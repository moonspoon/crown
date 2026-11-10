<?php
require __DIR__ . '/lib.php';

$holder = current_holder();
$person = $holder['id'] ? people()[$holder['id']] : null;
$lastHeld = last_held_dates();

header('Content-Type: application/json');
header('Cache-Control: no-store');
echo json_encode([
    'id' => $holder['id'],
    'name' => $person['name'] ?? null,
    'icon' => $person['icon'] ?? null,
    'since' => $holder['since'],
    // Everyone who can hold the crown, so clients (the Android app) can ask who they are.
    // last_held is when they last lost the crown: null if they've never held it or hold it now.
    'people' => array_map(fn($id, $p) => [
        'id' => $id,
        'name' => $p['name'],
        'icon' => $p['icon'],
        'last_held' => $lastHeld[$id] ?? null,
    ], array_keys(people()), people()),
]);

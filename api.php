<?php
require __DIR__ . '/lib.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

// POST action=login with id and password checks a login (the same ones as the website) for the Android app.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? null) === 'login') {
    $id = (string) ($_POST['id'] ?? '');
    if (check_login($id, (string) ($_POST['password'] ?? ''))) {
        $person = people()[$id];
        echo json_encode(['ok' => true, 'id' => $id, 'name' => $person['name'], 'icon' => $person['icon']]);
        exit;
    }
    // Slow down anyone guessing passwords.
    sleep(1);
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => match (true) {
        !isset(config()['people'][$id]) => 'Unknown person.',
        $id === superuser() && !admin_password_set() => 'No admin password is set up on the site yet.',
        !has_password($id) => "You don't have a password yet. Ask " . person_name(superuser()) . ' to set one.',
        default => 'Wrong password.',
    }]);
    exit;
}

$holder = current_holder();
$person = $holder['id'] ? people()[$holder['id']] : null;
$lastHeld = last_held_dates();

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

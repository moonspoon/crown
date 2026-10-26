<?php

const DATA_FILE = __DIR__ . '/data/holder.json';

function config(): array
{
    static $config = null;
    return $config ??= require __DIR__ . '/config.php';
}

function people(): array
{
    return config()['people'];
}

/** @return array{id: ?string, since: ?string} */
function current_holder(): array
{
    $state = is_file(DATA_FILE) ? json_decode((string) file_get_contents(DATA_FILE), true) : null;
    $id = $state['id'] ?? null;
    if (!isset(people()[$id])) {
        return ['id' => null, 'since' => null];
    }
    return ['id' => $id, 'since' => $state['since'] ?? null];
}

function set_holder(string $id): void
{
    if (!isset(people()[$id])) {
        throw new InvalidArgumentException('Unknown person');
    }
    // Re-selecting the current holder keeps their original "since".
    if (current_holder()['id'] === $id) {
        return;
    }
    if (!is_dir(dirname(DATA_FILE))) {
        mkdir(dirname(DATA_FILE), 0775, true);
    }
    $json = json_encode(['id' => $id, 'since' => date(DATE_ATOM)]);
    file_put_contents(DATA_FILE, $json, LOCK_EX);
}

function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function page_head(string $title): void
{
    ?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#1b1530">
<title><?= e($title) ?></title>
<link rel="manifest" href="manifest.webmanifest">
<link rel="icon" href="favicon.ico" sizes="32x32">
<link rel="icon" href="favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="apple-touch-icon.png">
<link rel="stylesheet" href="style.css">
</head>
<body>
<?php
}

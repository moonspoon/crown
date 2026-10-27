<?php

const DATA_FILE = __DIR__ . '/data/holder.json';
const LOG_FILE = __DIR__ . '/data/history.log';

function config(): array
{
    static $config = null;
    return $config ??= require __DIR__ . '/config.php';
}

function people(): array
{
    return config()['people'];
}

/** Alphabetical, whatever order config.php lists them in. */
function games(): array
{
    $games = config()['games'] ?? [];
    natcasesort($games);
    return array_values($games);
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

/** @param string[] $players ids of everyone else who played */
function set_holder(string $id, string $game, array $players): void
{
    if (!isset(people()[$id])) {
        throw new InvalidArgumentException('Unknown person.');
    }
    // Re-selecting the current holder keeps their original "since".
    $previous = current_holder()['id'];
    if ($previous === $id) {
        return;
    }
    if (!in_array($game, games(), true)) {
        throw new InvalidArgumentException('Pick which game was won.');
    }
    $players = array_values(array_unique(array_diff($players, [$id])));
    if (!$players) {
        throw new InvalidArgumentException('Pick who else played.');
    }
    foreach ($players as $player) {
        if (!isset(people()[$player])) {
            throw new InvalidArgumentException('Unknown person.');
        }
    }
    if (!is_dir(dirname(DATA_FILE))) {
        mkdir(dirname(DATA_FILE), 0775, true);
    }
    $now = date(DATE_ATOM);
    file_put_contents(DATA_FILE, json_encode(['id' => $id, 'since' => $now]), LOCK_EX);

    // One JSON object per line, oldest first.
    $entry = json_encode([
        'at' => $now,
        'from' => $previous,
        'to' => $id,
        'game' => $game,
        'players' => $players,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
    file_put_contents(LOG_FILE, $entry . "\n", FILE_APPEND | LOCK_EX);
}

/**
 * Removes the history entry recorded at $at for $to, keeping the chain of holders consistent:
 * the next entry takes over its "from", and deleting the latest entry hands the crown back.
 * @return bool false if no entry matched
 */
function delete_history_entry(string $at, string $to): bool
{
    if (!is_file(LOG_FILE)) {
        return false;
    }
    $entries = array_values(array_filter(array_map(
        fn($line) => json_decode($line, true),
        file(LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)
    )));
    foreach ($entries as $i => $entry) {
        if ($entry['at'] !== $at || $entry['to'] !== $to) {
            continue;
        }
        if (isset($entries[$i + 1])) {
            $entries[$i + 1]['from'] = $entry['from'];
        }
        array_splice($entries, $i, 1);
        $lines = array_map('json_encode', $entries);
        file_put_contents(LOG_FILE, $lines ? implode("\n", $lines) . "\n" : '', LOCK_EX);

        if ($i === count($entries)) {
            $last = end($entries) ?: null;
            file_put_contents(DATA_FILE, json_encode(['id' => $last['to'] ?? null, 'since' => $last['at'] ?? null]), LOCK_EX);
        }
        return true;
    }
    return false;
}

/** Most recent changes first; a null limit returns everything. */
function history(?int $limit = 50): array
{
    if (!is_file(LOG_FILE)) {
        return [];
    }
    $lines = file(LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($limit !== null) {
        $lines = array_slice($lines, -$limit);
    }
    $entries = array_map(fn($line) => json_decode($line, true), $lines);
    return array_reverse(array_filter($entries));
}

/**
 * Renders history() entries as a list, with times shown in the viewer's locale.
 * Pass the admin's CSRF token to add a delete button to each entry.
 */
function history_list(array $history, ?string $deleteToken = null): void
{
    if (!$history) {
        echo '<p class="since">No changes yet.</p>';
        return;
    }
    ?>
    <ol class="history">
        <?php foreach ($history as $h): ?>
            <li>
                <div>
                    <span><?= e(person_name($h['from'])) ?> → <strong><?= e(person_name($h['to'])) ?></strong></span>
                    <?php if (!empty($h['game'])): ?>
                        <small>
                            Won at <?= e($h['game']) ?>
                            against <?= e(implode(', ', array_map('person_name', $h['players'] ?? []))) ?>
                        </small>
                    <?php endif ?>
                </div>
                <div class="history-meta">
                    <time datetime="<?= e($h['at']) ?>"><?= e($h['at']) ?></time>
                    <?php if ($deleteToken !== null): ?>
                        <form method="post" class="delete-entry"
                              data-confirm="<?= e(person_name($h['to']) . (empty($h['game']) ? '' : ' winning ' . $h['game'])) ?>">
                            <input type="hidden" name="csrf" value="<?= e($deleteToken) ?>">
                            <input type="hidden" name="delete_at" value="<?= e($h['at']) ?>">
                            <input type="hidden" name="delete_to" value="<?= e($h['to']) ?>">
                            <button type="submit" class="link">Delete</button>
                        </form>
                    <?php endif ?>
                </div>
            </li>
        <?php endforeach ?>
    </ol>
    <script>
        document.querySelectorAll('.history time').forEach(t => {
            t.textContent = new Date(t.dateTime).toLocaleString();
        });
        document.querySelectorAll('.delete-entry').forEach(form => {
            form.addEventListener('submit', ev => {
                const when = new Date(form.delete_at.value).toLocaleDateString();
                if (!confirm(`Are you sure you want to delete ${form.dataset.confirm} on ${when}?`)) {
                    ev.preventDefault();
                }
            });
        });
    </script>
    <?php
}

function person_name(?string $id): string
{
    if ($id === null) {
        return 'Nobody';
    }
    return people()[$id]['name'] ?? $id;
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
<link rel="stylesheet" href="style.css?v=<?= filemtime(__DIR__ . '/style.css') ?>">
</head>
<body>
<?php
}
